"""Motor CP-SAT (OR-Tools) do EducaSync — MVP.

Modelo: variável booleana x[demanda, slot] (a demanda ocupa o slot).
- Cada demanda ocupa exatamente `qtd` slots distintos (cada ocorrência em 1 slot).
- Turma, professor e sala (se houver): no máximo 1 aula por slot.
- Disponibilidade: se houver registros para a entidade, só slots livre=true.
- Locks: demanda fixada num slot (reotimização: o resto da grade é recalculado).
- Soft `minimizar_janelas`: penaliza slots vazios entre a primeira e a última
  aula do dia de cada professor.
- Soft `evitar_disciplina_slot`: penaliza (peso * x) a matéria em slots que casam
  com `slot_id` e/ou `dia` e/ou `ordem` (não proíbe).
- Soft `concentrar_disciplina`: minimiza o nº de dias distintos usados por
  (turma, matéria); `turma_id` opcional.
Todos os termos soft somam no mesmo Minimize.
"""
from __future__ import annotations

import time
from collections import defaultdict

from ortools.sat.python import cp_model

from app.schemas import (
    Alocacao,
    Cenario,
    Metricas,
    RestricaoSoft,
    RespostaSolve,
    RespostaValidate,
)
from app.solver.metrics import calcular_metricas, posicao_por_slot

PESO_JANELAS_PADRAO = 100
PESO_EVITAR_SLOT_PADRAO = 50
PESO_CONCENTRAR_PADRAO = 50

HARD_SUPORTADAS = {"proibir_slot"}
SOFT_SUPORTADAS = {"minimizar_janelas", "evitar_disciplina_slot", "concentrar_disciplina"}

# Mapeamento explícito: nunca devolver OPTIMAL quando o CP-SAT disse FEASIBLE.
_STATUS = {
    cp_model.OPTIMAL: "OPTIMAL",
    cp_model.FEASIBLE: "FEASIBLE",
    cp_model.INFEASIBLE: "INFEASIBLE",
    cp_model.MODEL_INVALID: "MODEL_INVALID",
    cp_model.UNKNOWN: "UNKNOWN",
}


class ErroValidacaoCenario(Exception):
    def __init__(self, erros: list[str]):
        super().__init__("; ".join(erros))
        self.erros = erros


def validar_cenario(c: Cenario) -> RespostaValidate:
    erros: list[str] = []
    avisos: list[str] = []

    slot_ids = [s.id for s in c.slots]
    if len(slot_ids) != len(set(slot_ids)):
        erros.append("slots com id duplicado")
    posicoes = [(s.dia, s.ordem) for s in c.slots]
    if len(posicoes) != len(set(posicoes)):
        erros.append("slots com (dia, ordem) duplicado")
    slots_set = set(slot_ids)

    demanda_ids = [d.id for d in c.demandas]
    if len(demanda_ids) != len(set(demanda_ids)):
        erros.append("demandas com id duplicado")
    demandas_set = set(demanda_ids)

    for d in c.demandas:
        if d.professor_id is None:
            erros.append(f"demanda {d.id}: professor_id é obrigatório")
        if d.qtd > len(c.slots):
            avisos.append(
                f"demanda {d.id}: qtd={d.qtd} maior que o total de slots ({len(c.slots)})"
            )

    for disp in c.disponibilidades:
        if disp.slot_id not in slots_set:
            erros.append(
                f"disponibilidade ({disp.tipo} {disp.ref_id}): slot_id {disp.slot_id} inexistente"
            )

    for lock in c.locks:
        if lock.demanda_id not in demandas_set:
            erros.append(f"lock: demanda_id {lock.demanda_id} inexistente")
        if lock.slot_id not in slots_set:
            erros.append(f"lock: slot_id {lock.slot_id} inexistente")
    _validar_locks(c, erros, avisos)

    dias = {s.dia for s in c.slots}
    for r in c.restricoes_hard:
        if r.tipo not in HARD_SUPORTADAS:
            erros.append(f"restricao_hard '{r.tipo}' não suportada")
            continue
        if r.demanda_id is None or r.demanda_id not in demandas_set:
            erros.append(f"restricao_hard '{r.tipo}': demanda_id inexistente ou ausente")
        if r.slot_id is None and r.dia is None:
            erros.append(f"restricao_hard '{r.tipo}': informe slot_id ou dia")
        if r.slot_id is not None and r.slot_id not in slots_set:
            erros.append(f"restricao_hard '{r.tipo}': slot_id {r.slot_id} inexistente")
        if r.slot_id is None and r.dia is not None and r.dia not in dias:
            avisos.append(f"restricao_hard '{r.tipo}': dia {r.dia} sem slots")

    materias_demandas = {d.materia_id for d in c.demandas}
    turmas_demandas = {d.turma_id for d in c.demandas}
    for r in c.restricoes_soft:
        if r.tipo not in SOFT_SUPORTADAS:
            avisos.append(f"restricao_soft '{r.tipo}' não suportada (ignorada)")
        elif r.tipo == "evitar_disciplina_slot":
            _validar_soft_evitar(r, materias_demandas, slots_set, avisos)
        elif r.tipo == "concentrar_disciplina":
            _validar_soft_concentrar(r, materias_demandas, turmas_demandas, avisos)

    return RespostaValidate(valido=not erros, erros=erros, avisos=avisos)


def _campo_int(r: RestricaoSoft, nome: str) -> int | None:
    """Lê campo extra (opcional) do objeto soft como int; None se ausente/inválido."""
    valor = getattr(r, nome, None)
    if valor is None or isinstance(valor, bool):
        return None
    try:
        return int(valor)
    except (TypeError, ValueError):
        return None


def _validar_locks(c: Cenario, erros: list[str], avisos: list[str]) -> None:
    """Locks coerentes para reotimização: sem duplicatas e sem conflito entre si."""
    por_demanda = {d.id: d for d in c.demandas}
    vistos: set[tuple[int, int]] = set()
    locks_por_demanda: dict[int, int] = defaultdict(int)
    ocupacao: dict[tuple[str, int, int], int] = {}  # (atributo, ref, slot) -> demanda
    for lock in c.locks:
        d = por_demanda.get(lock.demanda_id)
        if d is None:
            continue  # já reportado como inexistente
        if (lock.demanda_id, lock.slot_id) in vistos:
            avisos.append(
                f"lock duplicado (demanda {lock.demanda_id}, slot {lock.slot_id}) ignorado"
            )
            continue
        vistos.add((lock.demanda_id, lock.slot_id))
        locks_por_demanda[lock.demanda_id] += 1
        for atributo in ("turma_id", "professor_id", "sala_id"):
            ref = getattr(d, atributo)
            if ref is None:
                continue
            chave = (atributo, ref, lock.slot_id)
            outra = ocupacao.get(chave)
            if outra is not None and outra != d.id:
                erros.append(
                    f"lock: demandas {outra} e {d.id} em conflito de {atributo[:-3]} "
                    f"{ref} no slot {lock.slot_id}"
                )
            else:
                ocupacao[chave] = d.id
    for did, n in locks_por_demanda.items():
        qtd = por_demanda[did].qtd
        if n > qtd:
            erros.append(f"lock: demanda {did} tem {n} locks, mas qtd={qtd}")


def _validar_soft_evitar(
    r: RestricaoSoft, materias: set[int], slots_set: set[int], avisos: list[str]
) -> None:
    mid = _campo_int(r, "materia_id")
    if mid is None:
        avisos.append("restricao_soft 'evitar_disciplina_slot': materia_id ausente (ignorada)")
    elif mid not in materias:
        avisos.append(
            f"restricao_soft 'evitar_disciplina_slot': materia_id {mid} sem demandas (sem efeito)"
        )
    sid = _campo_int(r, "slot_id")
    if sid is None and _campo_int(r, "dia") is None and _campo_int(r, "ordem") is None:
        avisos.append(
            "restricao_soft 'evitar_disciplina_slot': informe slot_id, dia e/ou ordem (ignorada)"
        )
    if sid is not None and sid not in slots_set:
        avisos.append(f"restricao_soft 'evitar_disciplina_slot': slot_id {sid} inexistente")


def _validar_soft_concentrar(
    r: RestricaoSoft, materias: set[int], turmas: set[int], avisos: list[str]
) -> None:
    mid = _campo_int(r, "materia_id")
    if mid is None:
        avisos.append("restricao_soft 'concentrar_disciplina': materia_id ausente (ignorada)")
    elif mid not in materias:
        avisos.append(
            f"restricao_soft 'concentrar_disciplina': materia_id {mid} sem demandas (sem efeito)"
        )
    tid = _campo_int(r, "turma_id")
    if tid is not None and tid not in turmas:
        avisos.append(
            f"restricao_soft 'concentrar_disciplina': turma_id {tid} sem demandas (sem efeito)"
        )


def _peso_janelas(c: Cenario) -> int:
    """Peso de minimizar_janelas; 0 se a soft não foi solicitada."""
    softs = [r for r in c.restricoes_soft if r.tipo == "minimizar_janelas"]
    if not softs:
        return 0
    peso = 0
    for r in softs:
        p = r.peso if r.peso is not None else c.pesos.get("janelas", PESO_JANELAS_PADRAO)
        peso += max(0, int(p))
    return peso


def _entidades_com_registro(c: Cenario) -> dict[tuple[str, int], set[int]]:
    """(tipo, ref_id) -> slots livres. Só existe chave se há registros."""
    livres: dict[tuple[str, int], set[int]] = {}
    for disp in c.disponibilidades:
        chave = (disp.tipo, disp.ref_id)
        livres.setdefault(chave, set())
        if disp.livre:
            livres[chave].add(disp.slot_id)
    return livres


def _peso_soft(c: Cenario, r: RestricaoSoft, padrao: int) -> int:
    """Peso: `peso` da soft > `pesos[tipo]` do cenário > padrão."""
    p = r.peso if r.peso is not None else c.pesos.get(r.tipo, padrao)
    return max(0, int(p))


def _soft_evitar_disciplina_slot(
    c: Cenario,
    x: dict[tuple[int, int], cp_model.IntVar],
    termos: list,
) -> None:
    """peso * x[demanda, slot] para a matéria nos slots que casam (soft, não proíbe)."""
    for r in c.restricoes_soft:
        if r.tipo != "evitar_disciplina_slot":
            continue
        mid = _campo_int(r, "materia_id")
        sid = _campo_int(r, "slot_id")
        dia = _campo_int(r, "dia")
        ordem = _campo_int(r, "ordem")
        peso = _peso_soft(c, r, PESO_EVITAR_SLOT_PADRAO)
        if mid is None or peso <= 0 or (sid is None and dia is None and ordem is None):
            continue
        alvos = {
            s.id
            for s in c.slots
            if (sid is None or s.id == sid)
            and (dia is None or s.dia == dia)
            and (ordem is None or s.ordem == ordem)
        }
        for d in c.demandas:
            if d.materia_id != mid:
                continue
            for slot_id in alvos:
                var = x.get((d.id, slot_id))
                if var is not None:
                    termos.append(peso * var)


def _soft_concentrar_disciplina(
    c: Cenario,
    model: cp_model.CpModel,
    x: dict[tuple[int, int], cp_model.IntVar],
    termos: list,
) -> None:
    """peso * nº de dias distintos usados por (turma, matéria)."""
    slots_por_dia: dict[int, list[int]] = defaultdict(list)
    for s in c.slots:
        slots_por_dia[s.dia].append(s.id)

    for idx, r in enumerate(c.restricoes_soft):
        if r.tipo != "concentrar_disciplina":
            continue
        mid = _campo_int(r, "materia_id")
        tid = _campo_int(r, "turma_id")
        peso = _peso_soft(c, r, PESO_CONCENTRAR_PADRAO)
        if mid is None or peso <= 0:
            continue
        demandas_por_turma: dict[int, list] = defaultdict(list)
        for d in c.demandas:
            if d.materia_id == mid and (tid is None or d.turma_id == tid):
                demandas_por_turma[d.turma_id].append(d)
        for turma, demandas in demandas_por_turma.items():
            if sum(d.qtd for d in demandas) <= 1:
                continue  # 1 aula só: sempre 1 dia, sem efeito na decisão
            for dia, sids in slots_por_dia.items():
                vars_dia = [x[(d.id, sid)] for d in demandas for sid in sids if (d.id, sid) in x]
                if not vars_dia:
                    continue
                usa = model.NewBoolVar(f"usa_dia_{idx}_{turma}_{mid}_{dia}")
                model.AddMaxEquality(usa, vars_dia)  # usa == OR(vars_dia)
                termos.append(peso * usa)


def resolver(c: Cenario, *, max_time_s_limite: float, max_workers_limite: int) -> RespostaSolve:
    inicio = time.perf_counter()

    validacao = validar_cenario(c)
    if not validacao.valido:
        raise ErroValidacaoCenario(validacao.erros)

    slots_ids = [s.id for s in c.slots]
    livres = _entidades_com_registro(c)

    def slot_livre(tipo: str, ref_id: int | None, slot_id: int) -> bool:
        if ref_id is None:
            return True
        chave = (tipo, ref_id)
        return chave not in livres or slot_id in livres[chave]

    proibidos: set[tuple[int, int]] = set()
    slots_por_dia: dict[int, list[int]] = defaultdict(list)
    for s in c.slots:
        slots_por_dia[s.dia].append(s.id)
    for r in c.restricoes_hard:
        alvos = [r.slot_id] if r.slot_id is not None else slots_por_dia.get(r.dia, [])
        for sid in alvos:
            proibidos.add((int(r.demanda_id), sid))  # type: ignore[arg-type]

    model = cp_model.CpModel()
    x: dict[tuple[int, int], cp_model.IntVar] = {}
    for d in c.demandas:
        for sid in slots_ids:
            if (
                slot_livre("professor", d.professor_id, sid)
                and slot_livre("turma", d.turma_id, sid)
                and slot_livre("sala", d.sala_id, sid)
                and (d.id, sid) not in proibidos
            ):
                x[(d.id, sid)] = model.NewBoolVar(f"x_{d.id}_{sid}")

    # Cada ocorrência da demanda em exatamente 1 slot (e no máx. 1 por slot).
    for d in c.demandas:
        model.Add(sum(x[(d.id, sid)] for sid in slots_ids if (d.id, sid) in x) == d.qtd)

    # Conflitos por slot: turma, professor, sala.
    for sid in slots_ids:
        for atributo in ("turma_id", "professor_id", "sala_id"):
            grupos: dict[int, list[cp_model.IntVar]] = defaultdict(list)
            for d in c.demandas:
                ref = getattr(d, atributo)
                if ref is not None and (d.id, sid) in x:
                    grupos[ref].append(x[(d.id, sid)])
            for vars_ in grupos.values():
                if len(vars_) > 1:
                    model.AddAtMostOne(vars_)

    # Locks: demanda fixada no slot (reotimização). Duplicatas são ignoradas.
    for chave in dict.fromkeys((lk.demanda_id, lk.slot_id) for lk in c.locks):
        var = x.get(chave)
        if var is None:
            # Slot indisponível/proibido para a demanda => modelo inviável.
            model.AddBoolOr([])
        else:
            model.Add(var == 1)

    termos_objetivo: list = []

    # Soft: minimizar janelas por professor/dia.
    peso = _peso_janelas(c)
    if peso > 0:
        pos = posicao_por_slot(c.slots)
        ordenados_por_dia: dict[int, list[int]] = defaultdict(list)
        for sid, (dia, _p) in sorted(pos.items(), key=lambda kv: (kv[1][0], kv[1][1])):
            ordenados_por_dia[dia].append(sid)

        demandas_por_prof: dict[int, list[int]] = defaultdict(list)
        for d in c.demandas:
            demandas_por_prof[int(d.professor_id)].append(d.id)  # type: ignore[arg-type]

        for prof_id, dem_ids in demandas_por_prof.items():
            for dia, seq in ordenados_por_dia.items():
                n = len(seq)
                if n < 3:
                    continue
                # occ[i] ∈ {0,1} (professor tem no máx. 1 aula por slot).
                occ = [
                    sum(x[(did, sid)] for did in dem_ids if (did, sid) in x) for sid in seq
                ]
                antes = [model.NewBoolVar(f"antes_{prof_id}_{dia}_{i}") for i in range(n)]
                depois = [model.NewBoolVar(f"depois_{prof_id}_{dia}_{i}") for i in range(n)]
                for i in range(1, n):
                    model.Add(antes[i] >= occ[i - 1])
                    if i >= 2:
                        model.Add(antes[i] >= antes[i - 1])
                for i in range(n - 2, -1, -1):
                    model.Add(depois[i] >= occ[i + 1])
                    if i <= n - 3:
                        model.Add(depois[i] >= depois[i + 1])
                for i in range(1, n - 1):
                    gap = model.NewBoolVar(f"gap_{prof_id}_{dia}_{i}")
                    model.Add(gap >= antes[i] + depois[i] - 1 - occ[i])
                    termos_objetivo.append(peso * gap)

    _soft_evitar_disciplina_slot(c, x, termos_objetivo)
    _soft_concentrar_disciplina(c, model, x, termos_objetivo)

    if termos_objetivo:
        model.Minimize(sum(termos_objetivo))

    solver = cp_model.CpSolver()
    solver.parameters.max_time_in_seconds = min(c.limites.max_time_s, max_time_s_limite)
    solver.parameters.num_workers = min(c.limites.num_workers, max_workers_limite)
    status_cp = solver.Solve(model)
    status = _STATUS.get(status_cp, "UNKNOWN")

    alocacoes: list[Alocacao] = []
    objetivo: float | None = None
    best_bound: float | None = None

    if status_cp in (cp_model.OPTIMAL, cp_model.FEASIBLE):
        por_id = {d.id: d for d in c.demandas}
        for (did, sid), var in sorted(x.items()):
            if solver.BooleanValue(var):
                d = por_id[did]
                alocacoes.append(
                    Alocacao(
                        demanda_id=did,
                        slot_id=sid,
                        professor_id=d.professor_id,
                        sala_id=d.sala_id,
                    )
                )
        objetivo = float(solver.ObjectiveValue()) if termos_objetivo else 0.0
        best_bound = float(solver.BestObjectiveBound()) if termos_objetivo else 0.0

    tempo_ms = int((time.perf_counter() - inicio) * 1000)
    janelas_total = 0
    if alocacoes:
        janelas_total, _ = calcular_metricas(c.slots, c.demandas, alocacoes)

    return RespostaSolve(
        status=status,  # type: ignore[arg-type]
        alocacoes=alocacoes,
        metricas=Metricas(
            aulas_alocadas=len(alocacoes), janelas_total=janelas_total, tempo_ms=tempo_ms
        ),
        objetivo=objetivo,
        best_bound=best_bound,
        tempo_ms=tempo_ms,
    )
