"""Contratos JSON (Pydantic v2) do solver EducaSync."""
from __future__ import annotations

from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, Field


class Slot(BaseModel):
    id: int
    dia: int
    ordem: int
    horario_de: str | None = None
    horario_ate: str | None = None


class Demanda(BaseModel):
    id: int
    turma_id: int
    materia_id: int
    qtd: int = Field(ge=1)
    # Opcional no schema para que a ausência vire erro de validação do cenário
    # (mensagem clara por demanda) e não um 422 genérico do Pydantic.
    professor_id: int | None = None
    sala_id: int | None = None


class Disponibilidade(BaseModel):
    tipo: Literal["professor", "turma", "sala"]
    ref_id: int
    slot_id: int
    livre: bool = True


class RestricaoHard(BaseModel):
    """Restrição obrigatória. Suportado: `proibir_slot` (demanda_id + slot_id ou dia)."""

    model_config = ConfigDict(extra="allow")

    tipo: str
    demanda_id: int | None = None
    slot_id: int | None = None
    dia: int | None = None


class RestricaoSoft(BaseModel):
    """Preferência (soft). Campos extras são aceitos (`extra="allow"`).

    Tipos suportados (peso: `peso` da soft > `pesos[tipo]` > padrão):
    - `minimizar_janelas`: penaliza janelas de professor (padrão: `pesos["janelas"]`).
    - `evitar_disciplina_slot`: `{materia_id, ordem?, dia?, slot_id?}` — soma
      `peso * x` das aulas da matéria nos slots que casam com todos os filtros
      informados (ao menos um). Não proíbe.
    - `concentrar_disciplina`: `{materia_id, turma_id?}` — minimiza o nº de dias
      distintos usados pela matéria em cada turma (todas as turmas da matéria
      se `turma_id` for omitido).
    Tipo desconhecido ou campos inválidos geram aviso em `/validate`, não erro.
    """

    model_config = ConfigDict(extra="allow")

    tipo: str
    peso: int | None = Field(default=None, ge=0)


class Lock(BaseModel):
    """Fixa a demanda em um slot (aula já decidida; usado na reotimização).

    Pode haver vários locks por demanda (até `qtd`); o restante fica livre.
    """

    demanda_id: int
    slot_id: int


class Limites(BaseModel):
    max_time_s: float = Field(default=60, gt=0)
    num_workers: int = Field(default=4, ge=1)


class Cenario(BaseModel):
    cenario_id: int | None = None
    slots: list[Slot] = Field(default_factory=list)
    demandas: list[Demanda] = Field(default_factory=list)
    disponibilidades: list[Disponibilidade] = Field(default_factory=list)
    restricoes_hard: list[RestricaoHard] = Field(default_factory=list)
    restricoes_soft: list[RestricaoSoft] = Field(default_factory=list)
    pesos: dict[str, int] = Field(default_factory=dict)
    locks: list[Lock] = Field(default_factory=list)
    limites: Limites = Field(default_factory=Limites)


class Alocacao(BaseModel):
    demanda_id: int
    slot_id: int
    professor_id: int | None = None
    sala_id: int | None = None


class Metricas(BaseModel):
    aulas_alocadas: int = 0
    janelas_total: int = 0
    tempo_ms: int = 0


StatusSolver = Literal["OPTIMAL", "FEASIBLE", "INFEASIBLE", "MODEL_INVALID", "UNKNOWN"]


class RespostaSolve(BaseModel):
    status: StatusSolver
    alocacoes: list[Alocacao] = Field(default_factory=list)
    metricas: Metricas = Field(default_factory=Metricas)
    objetivo: float | None = None
    best_bound: float | None = None
    tempo_ms: int = 0


class RespostaValidate(BaseModel):
    valido: bool
    erros: list[str] = Field(default_factory=list)
    avisos: list[str] = Field(default_factory=list)


class MetricasRequest(BaseModel):
    """Calcula métricas de uma grade já montada (sem resolver)."""

    cenario_id: int | None = None
    slots: list[Slot] = Field(default_factory=list)
    demandas: list[Demanda] = Field(default_factory=list)
    alocacoes: list[Alocacao] = Field(default_factory=list)


class RespostaMetricas(BaseModel):
    metricas: Metricas
    janelas_por_professor: dict[str, int] = Field(default_factory=dict)
    conflitos: list[dict[str, Any]] = Field(default_factory=list)
