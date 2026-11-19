"""Testes do motor CP-SAT (MVP) e da API."""
from __future__ import annotations

import os

os.environ["INTERNAL_API_KEY"] = "chave_teste"

import pytest
from fastapi.testclient import TestClient

from app.main import app
from app.schemas import Cenario
from app.solver.cpsat import ErroValidacaoCenario, resolver

HEADERS = {"X-Internal-Api-Key": "chave_teste"}


def _slots(dia: int, n: int, inicio_id: int = 1) -> list[dict]:
    return [
        {"id": inicio_id + i, "dia": dia, "ordem": i + 1,
         "horario_de": f"{7 + i:02d}:00", "horario_ate": f"{7 + i:02d}:50"}
        for i in range(n)
    ]


def _resolver(payload: dict):
    return resolver(Cenario(**payload), max_time_s_limite=30, max_workers_limite=4)


def _demanda(id_, turma, prof, qtd=1, materia=5, sala=None) -> dict:
    return {"id": id_, "turma_id": turma, "materia_id": materia, "qtd": qtd,
            "professor_id": prof, "sala_id": sala}


def test_caso_simples_duas_alocacoes():
    r = _resolver({
        "cenario_id": 1,
        "slots": _slots(1, 3),
        "demandas": [_demanda(10, 3, 9, qtd=2)],
        "disponibilidades": [
            {"tipo": "professor", "ref_id": 9, "slot_id": 1, "livre": True},
            {"tipo": "professor", "ref_id": 9, "slot_id": 2, "livre": True},
        ],
        "restricoes_soft": [{"tipo": "minimizar_janelas", "peso": 100}],
        "pesos": {"janelas": 100},
    })
    assert r.status in ("OPTIMAL", "FEASIBLE")
    assert len(r.alocacoes) == 2
    assert r.metricas.aulas_alocadas == 2
    assert all(a.demanda_id == 10 and a.professor_id == 9 for a in r.alocacoes)
    assert len({a.slot_id for a in r.alocacoes}) == 2


def test_impossivel_infeasible():
    # Professor 9 só está livre em 1 slot, mas precisa de 2 aulas.
    r = _resolver({
        "slots": _slots(1, 3),
        "demandas": [_demanda(10, 3, 9, qtd=2)],
        "disponibilidades": [
            {"tipo": "professor", "ref_id": 9, "slot_id": 1, "livre": True},
            {"tipo": "professor", "ref_id": 9, "slot_id": 2, "livre": False},
        ],
    })
    assert r.status == "INFEASIBLE"
    assert r.alocacoes == []


def test_conflito_de_professor_infeasible():
    # Dois turmas, mesmo professor, 1 único slot.
    r = _resolver({
        "slots": _slots(1, 1),
        "demandas": [_demanda(1, 1, 9), _demanda(2, 2, 9)],
    })
    assert r.status == "INFEASIBLE"


def test_prefere_slots_contiguos():
    # Turma 1 tem aula travada no slot 2 (outro professor). O professor 9 precisa
    # de 2 aulas na mesma turma: 1+3 teria janela; 3+4 ou 4+5 não.
    r = _resolver({
        "slots": _slots(1, 5),
        "demandas": [_demanda(10, 1, 9, qtd=2), _demanda(11, 1, 7, qtd=1, materia=6)],
        "locks": [{"demanda_id": 11, "slot_id": 2}],
        "restricoes_soft": [{"tipo": "minimizar_janelas", "peso": 100}],
        "pesos": {"janelas": 100},
    })
    assert r.status == "OPTIMAL"
    assert r.metricas.janelas_total == 0
    assert r.objetivo == 0
    slots_prof9 = sorted(a.slot_id for a in r.alocacoes if a.demanda_id == 10)
    assert slots_prof9[1] - slots_prof9[0] == 1
    assert 2 not in slots_prof9


def test_janela_inevitavel_e_contada():
    # Slots 1 e 3 são os únicos livres => 1 janela (slot 2) inevitável.
    r = _resolver({
        "slots": _slots(1, 3),
        "demandas": [_demanda(10, 1, 9, qtd=2)],
        "disponibilidades": [
            {"tipo": "professor", "ref_id": 9, "slot_id": 1, "livre": True},
            {"tipo": "professor", "ref_id": 9, "slot_id": 3, "livre": True},
        ],
        "restricoes_soft": [{"tipo": "minimizar_janelas", "peso": 10}],
    })
    assert r.status == "OPTIMAL"
    assert r.metricas.janelas_total == 1
    assert r.objetivo == 10


def test_professor_nulo_erro_de_validacao():
    with pytest.raises(ErroValidacaoCenario):
        _resolver({"slots": _slots(1, 2), "demandas": [_demanda(10, 1, None)]})


def test_api_auth_e_endpoints():
    client = TestClient(app)
    assert client.get("/health").status_code == 200

    corpo = {"slots": _slots(1, 3), "demandas": [_demanda(10, 3, 9, qtd=2)]}
    assert client.post("/solve", json=corpo).status_code == 401
    assert client.post("/solve", json=corpo, headers={"X-Internal-Api-Key": "x"}).status_code == 401

    resp = client.post("/solve", json=corpo, headers=HEADERS)
    assert resp.status_code == 200
    assert len(resp.json()["alocacoes"]) == 2

    invalido = {"slots": _slots(1, 2), "demandas": [_demanda(10, 1, None)]}
    assert client.post("/solve", json=invalido, headers=HEADERS).status_code == 422
    v = client.post("/validate", json=invalido, headers=HEADERS).json()
    assert v["valido"] is False

    m = client.post("/metrics", json={
        "slots": _slots(1, 3),
        "demandas": [_demanda(10, 1, 9, qtd=2)],
        "alocacoes": [{"demanda_id": 10, "slot_id": 1, "professor_id": 9},
                      {"demanda_id": 10, "slot_id": 3, "professor_id": 9}],
    }, headers=HEADERS).json()
    assert m["metricas"]["janelas_total"] == 1


# --- Soft constraints: evitar_disciplina_slot / concentrar_disciplina ----------


def _cenario_duas_aulas(soft: list[dict]) -> dict:
    # Turma 1, 2 slots, 2 demandas (matéria 4 e 6): qualquer ordem é viável.
    return {
        "slots": _slots(1, 2),
        "demandas": [_demanda(10, 1, 9, materia=4), _demanda(11, 1, 7, materia=6)],
        "restricoes_soft": soft,
    }


def _slot_da(r, demanda_id: int) -> int:
    return next(a.slot_id for a in r.alocacoes if a.demanda_id == demanda_id)


def test_evitar_disciplina_slot_por_ordem_evita_ultimo_horario():
    r = _resolver(_cenario_duas_aulas([
        {"tipo": "evitar_disciplina_slot", "peso": 50, "materia_id": 4, "ordem": 2},
    ]))
    assert r.status == "OPTIMAL"
    assert _slot_da(r, 10) == 1  # matéria 4 fora da ordem 2
    assert r.objetivo == 0


def test_evitar_disciplina_slot_por_dia_e_ordem_e_por_slot_id():
    base = _cenario_duas_aulas([])
    base["slots"] = _slots(1, 2) + _slots(2, 2, inicio_id=3)  # dia 2: ids 3,4
    base["demandas"] = [_demanda(10, 1, 9, materia=4)]
    # Evita (dia=1, ordem=1) e o slot 2 => sobram os slots do dia 2.
    base["restricoes_soft"] = [
        {"tipo": "evitar_disciplina_slot", "peso": 50, "materia_id": 4, "dia": 1, "ordem": 1},
        {"tipo": "evitar_disciplina_slot", "peso": 50, "materia_id": 4, "slot_id": 2},
        {"tipo": "evitar_disciplina_slot", "peso": 50, "materia_id": 4, "dia": 2, "ordem": 2},
    ]
    r = _resolver(base)
    assert r.status == "OPTIMAL"
    assert _slot_da(r, 10) == 3  # único slot sem penalidade
    assert r.objetivo == 0


def test_evitar_disciplina_slot_e_soft_nao_hard():
    # Único slot: a penalidade é inevitável, mas o cenário continua viável.
    r = _resolver({
        "slots": _slots(1, 1),
        "demandas": [_demanda(10, 1, 9, materia=4)],
        "restricoes_soft": [
            {"tipo": "evitar_disciplina_slot", "peso": 50, "materia_id": 4, "ordem": 1},
        ],
    })
    assert r.status == "OPTIMAL"
    assert len(r.alocacoes) == 1
    assert r.objetivo == 50


def test_evitar_disciplina_slot_outra_materia_nao_e_afetada():
    # Soft só da matéria 4: a matéria 6 pode ficar na ordem 2 sem custo.
    r = _resolver(_cenario_duas_aulas([
        {"tipo": "evitar_disciplina_slot", "peso": 50, "materia_id": 4, "ordem": 1},
    ]))
    assert _slot_da(r, 10) == 2
    assert _slot_da(r, 11) == 1
    assert r.objetivo == 0


def test_concentrar_disciplina_prefere_mesmo_dia():
    # Dia 1 (slots 1,2) e dia 2 (slots 3,4). A aula da matéria 6 trava o slot 1,
    # então a matéria 4 (2 aulas) só cabe junta no dia 2 para usar 1 dia só.
    r = _resolver({
        "slots": _slots(1, 2) + _slots(2, 2, inicio_id=3),
        "demandas": [_demanda(10, 1, 9, qtd=2, materia=4), _demanda(11, 1, 7, materia=6)],
        "locks": [{"demanda_id": 11, "slot_id": 1}],
        "restricoes_soft": [{"tipo": "concentrar_disciplina", "peso": 50, "materia_id": 4}],
    })
    assert r.status == "OPTIMAL"
    assert sorted(a.slot_id for a in r.alocacoes if a.demanda_id == 10) == [3, 4]
    assert r.objetivo == 50  # 1 dia distinto * peso


def test_concentrar_disciplina_dias_inevitaveis_sao_contados():
    # Professor só livre nos slots 1 (dia 1) e 3 (dia 2): 2 dias inevitáveis.
    r = _resolver({
        "slots": _slots(1, 2) + _slots(2, 2, inicio_id=3),
        "demandas": [_demanda(10, 1, 9, qtd=2, materia=4)],
        "disponibilidades": [
            {"tipo": "professor", "ref_id": 9, "slot_id": 1, "livre": True},
            {"tipo": "professor", "ref_id": 9, "slot_id": 2, "livre": False},
            {"tipo": "professor", "ref_id": 9, "slot_id": 3, "livre": True},
            {"tipo": "professor", "ref_id": 9, "slot_id": 4, "livre": False},
        ],
        "restricoes_soft": [{"tipo": "concentrar_disciplina", "peso": 10, "materia_id": 4}],
    })
    assert r.status == "OPTIMAL"
    assert r.objetivo == 20


def test_concentrar_disciplina_filtra_por_turma():
    # Duas turmas com a matéria 4; só a turma 2 é concentrada. A turma 1 tem
    # as duas aulas forçadas em dias distintos (sem custo, pois não é afetada).
    r = _resolver({
        "slots": _slots(1, 2) + _slots(2, 2, inicio_id=3),
        "demandas": [
            _demanda(10, 1, 9, qtd=2, materia=4),
            _demanda(20, 2, 8, qtd=2, materia=4),
        ],
        "disponibilidades": [
            {"tipo": "professor", "ref_id": 9, "slot_id": 1, "livre": True},
            {"tipo": "professor", "ref_id": 9, "slot_id": 3, "livre": True},
        ],
        "restricoes_soft": [
            {"tipo": "concentrar_disciplina", "peso": 50, "materia_id": 4, "turma_id": 2},
        ],
    })
    assert r.status == "OPTIMAL"
    assert r.objetivo == 50  # só a turma 2 conta, em 1 dia
    dias = {s["id"]: s["dia"] for s in _slots(1, 2) + _slots(2, 2, inicio_id=3)}
    assert len({dias[a.slot_id] for a in r.alocacoes if a.demanda_id == 20}) == 1


def test_somas_soft_no_mesmo_objetivo():
    r = _resolver({
        "slots": _slots(1, 3),
        "demandas": [_demanda(10, 1, 9, qtd=2, materia=4)],
        "disponibilidades": [
            {"tipo": "professor", "ref_id": 9, "slot_id": 1, "livre": True},
            {"tipo": "professor", "ref_id": 9, "slot_id": 3, "livre": True},
        ],
        "restricoes_soft": [
            {"tipo": "minimizar_janelas", "peso": 10},
            {"tipo": "evitar_disciplina_slot", "peso": 5, "materia_id": 4, "ordem": 3},
            {"tipo": "concentrar_disciplina", "peso": 7, "materia_id": 4},
        ],
    })
    # janela(10) + aula na ordem 3 (5) + 1 dia (7)
    assert r.status == "OPTIMAL"
    assert r.objetivo == 22


def test_validate_softs_novas_avisos_nao_erros():
    cenario = {
        "slots": _slots(1, 2),
        "demandas": [_demanda(10, 1, 9, materia=4)],
        "restricoes_soft": [
            {"tipo": "evitar_disciplina_slot", "peso": 5, "materia_id": 99, "ordem": 1},
            {"tipo": "evitar_disciplina_slot", "peso": 5, "materia_id": 4},
            {"tipo": "concentrar_disciplina", "peso": 5},
            {"tipo": "concentrar_disciplina", "peso": 5, "materia_id": 4, "turma_id": 77},
            {"tipo": "soft_inventada", "peso": 1},
        ],
    }
    v = TestClient(app).post("/validate", json=cenario, headers=HEADERS).json()
    assert v["valido"] is True
    assert len(v["avisos"]) == 5
    # E o solve ignora o que é inválido, sem quebrar.
    r = _resolver(cenario)
    assert r.status == "OPTIMAL"
    assert r.objetivo == 0


# --- Locks / reotimização ---------------------------------------------------------


def test_reotimizacao_lock_na_segunda_resto_livre():
    # Dia 1 (segunda): slots 1-3 | dia 2: slots 4-6. Aulas de segunda travadas,
    # o restante da grade é recalculado respeitando os locks.
    r = _resolver({
        "slots": _slots(1, 3) + _slots(2, 3, inicio_id=4),
        "demandas": [
            _demanda(10, 1, 9, qtd=2, materia=4),
            _demanda(11, 1, 7, qtd=2, materia=6),
            _demanda(12, 2, 9, qtd=2, materia=5),
        ],
        "locks": [
            {"demanda_id": 10, "slot_id": 1},
            {"demanda_id": 11, "slot_id": 2},
            {"demanda_id": 12, "slot_id": 3},
        ],
        "restricoes_soft": [{"tipo": "minimizar_janelas", "peso": 10}],
    })
    assert r.status in ("OPTIMAL", "FEASIBLE")
    assert len(r.alocacoes) == 6
    por_slot = {(a.demanda_id, a.slot_id) for a in r.alocacoes}
    assert {(10, 1), (11, 2), (12, 3)} <= por_slot
    # Cada demanda ganhou a 2ª aula num slot livre (distinto do lock).
    for did, lock_slot in ((10, 1), (11, 2), (12, 3)):
        slots_d = [a.slot_id for a in r.alocacoes if a.demanda_id == did]
        assert len(set(slots_d)) == 2 and lock_slot in slots_d
    # Sem conflito de professor/turma no mesmo slot.
    assert len({(a.slot_id, a.professor_id) for a in r.alocacoes}) == 6
    turma_de = {10: 1, 11: 1, 12: 2}
    assert len({(a.slot_id, turma_de[a.demanda_id]) for a in r.alocacoes}) == 6


def test_lock_duplicado_e_ignorado_com_aviso():
    cenario = {
        "slots": _slots(1, 2),
        "demandas": [_demanda(10, 1, 9)],
        "locks": [{"demanda_id": 10, "slot_id": 1}, {"demanda_id": 10, "slot_id": 1}],
    }
    v = TestClient(app).post("/validate", json=cenario, headers=HEADERS).json()
    assert v["valido"] is True and len(v["avisos"]) == 1
    r = _resolver(cenario)
    assert r.status == "OPTIMAL" and _slot_da(r, 10) == 1


def test_locks_conflitantes_viram_erro_de_validacao():
    # Mesma turma e mesmo slot para duas demandas distintas.
    with pytest.raises(ErroValidacaoCenario):
        _resolver({
            "slots": _slots(1, 2),
            "demandas": [_demanda(10, 1, 9), _demanda(11, 1, 7, materia=6)],
            "locks": [{"demanda_id": 10, "slot_id": 1}, {"demanda_id": 11, "slot_id": 1}],
        })
    # Mais locks do que aulas da demanda.
    with pytest.raises(ErroValidacaoCenario):
        _resolver({
            "slots": _slots(1, 3),
            "demandas": [_demanda(10, 1, 9, qtd=1)],
            "locks": [{"demanda_id": 10, "slot_id": 1}, {"demanda_id": 10, "slot_id": 2}],
        })
