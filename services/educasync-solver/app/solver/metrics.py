"""Métricas de uma grade horária (independentes do CP-SAT)."""
from __future__ import annotations

from collections import defaultdict
from collections.abc import Iterable

from app.schemas import Alocacao, Demanda, Slot


def contar_janelas(ordens_ocupadas: Iterable[int]) -> int:
    """Janelas = posições vazias entre a primeira e a última aula do dia.

    `ordens_ocupadas` são os índices de posição (0..n-1) já ranqueados por
    `ordem` dentro do dia.
    """
    pos = sorted(set(ordens_ocupadas))
    if len(pos) < 2:
        return 0
    return (pos[-1] - pos[0] + 1) - len(pos)


def posicao_por_slot(slots: list[Slot]) -> dict[int, tuple[int, int]]:
    """slot_id -> (dia, posição ranqueada por `ordem` dentro do dia)."""
    por_dia: dict[int, list[Slot]] = defaultdict(list)
    for s in slots:
        por_dia[s.dia].append(s)
    resultado: dict[int, tuple[int, int]] = {}
    for dia, lista in por_dia.items():
        for pos, s in enumerate(sorted(lista, key=lambda x: (x.ordem, x.id))):
            resultado[s.id] = (dia, pos)
    return resultado


def janelas_por_professor(
    slots: list[Slot], pares_professor_slot: Iterable[tuple[int, int]]
) -> dict[int, int]:
    pos = posicao_por_slot(slots)
    ocupado: dict[tuple[int, int], set[int]] = defaultdict(set)
    professores: set[int] = set()
    for prof_id, slot_id in pares_professor_slot:
        if slot_id not in pos:
            continue
        dia, p = pos[slot_id]
        ocupado[(prof_id, dia)].add(p)
        professores.add(prof_id)
    total: dict[int, int] = {p: 0 for p in professores}
    for (prof_id, _dia), posicoes in ocupado.items():
        total[prof_id] += contar_janelas(posicoes)
    return total


def detectar_conflitos(
    demandas: list[Demanda], alocacoes: list[Alocacao]
) -> list[dict]:
    """Conflitos de professor, turma ou sala no mesmo slot."""
    por_id = {d.id: d for d in demandas}
    usados: dict[tuple[str, int, int], list[int]] = defaultdict(list)
    for a in alocacoes:
        d = por_id.get(a.demanda_id)
        professor = a.professor_id if a.professor_id is not None else (d.professor_id if d else None)
        sala = a.sala_id if a.sala_id is not None else (d.sala_id if d else None)
        if professor is not None:
            usados[("professor", professor, a.slot_id)].append(a.demanda_id)
        if d is not None:
            usados[("turma", d.turma_id, a.slot_id)].append(a.demanda_id)
        if sala is not None:
            usados[("sala", sala, a.slot_id)].append(a.demanda_id)
    return [
        {"tipo": tipo, "ref_id": ref, "slot_id": slot, "demanda_ids": ids}
        for (tipo, ref, slot), ids in sorted(usados.items())
        if len(ids) > 1
    ]


def calcular_metricas(
    slots: list[Slot],
    demandas: list[Demanda],
    alocacoes: list[Alocacao],
) -> tuple[int, dict[int, int]]:
    """Retorna (janelas_total, janelas por professor)."""
    por_id = {d.id: d for d in demandas}
    pares = []
    for a in alocacoes:
        prof = a.professor_id
        if prof is None and a.demanda_id in por_id:
            prof = por_id[a.demanda_id].professor_id
        if prof is not None:
            pares.append((prof, a.slot_id))
    por_prof = janelas_por_professor(slots, pares)
    return sum(por_prof.values()), por_prof
