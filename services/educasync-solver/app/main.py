"""Ponto de entrada FastAPI do EducaSync Solver (stateless: JSON in, JSON out)."""
from __future__ import annotations

import logging

from fastapi import Depends, FastAPI
from fastapi.responses import JSONResponse

from app.auth import require_internal_api_key
from app.config import get_settings
from app.schemas import (
    Cenario,
    Metricas,
    MetricasRequest,
    RespostaMetricas,
    RespostaSolve,
    RespostaValidate,
)
from app.solver.cpsat import ErroValidacaoCenario, resolver, validar_cenario
from app.solver.metrics import calcular_metricas, detectar_conflitos

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(name)s %(message)s",
)

settings = get_settings()

app = FastAPI(
    title="EducaSync Solver",
    description="Solver CP-SAT de grade horária para o EducaSync (EducaTudo). Sem estado, sem banco.",
    version="0.1.0",
)


@app.exception_handler(ErroValidacaoCenario)
async def erro_validacao_cenario(request, exc: ErroValidacaoCenario):  # noqa: ANN001
    return JSONResponse(
        status_code=422,
        content={"error": "validation_error", "erros": exc.erros},
    )


@app.exception_handler(Exception)
async def unhandled_exception_handler(request, exc):  # noqa: ANN001
    logging.getLogger("educasync_solver.main").exception(
        "erro_nao_tratado path=%s tipo_erro=%s", request.url.path, type(exc).__name__
    )
    return JSONResponse(status_code=500, content={"error": "internal_server_error"})


@app.get("/health")
async def health() -> dict:
    return {"status": "ok", "service": settings.app_name}


@app.post("/solve", response_model=RespostaSolve, dependencies=[Depends(require_internal_api_key)])
def solve(cenario: Cenario) -> RespostaSolve:
    # def síncrono: o FastAPI roda em threadpool e não bloqueia o event loop.
    return resolver(
        cenario,
        max_time_s_limite=settings.max_time_s_limite,
        max_workers_limite=settings.max_workers_limite,
    )


@app.post(
    "/validate", response_model=RespostaValidate, dependencies=[Depends(require_internal_api_key)]
)
def validate(cenario: Cenario) -> RespostaValidate:
    return validar_cenario(cenario)


@app.post(
    "/metrics", response_model=RespostaMetricas, dependencies=[Depends(require_internal_api_key)]
)
def metrics(req: MetricasRequest) -> RespostaMetricas:
    janelas_total, por_prof = calcular_metricas(req.slots, req.demandas, req.alocacoes)
    return RespostaMetricas(
        metricas=Metricas(aulas_alocadas=len(req.alocacoes), janelas_total=janelas_total),
        janelas_por_professor={str(k): v for k, v in sorted(por_prof.items())},
        conflitos=detectar_conflitos(req.demandas, req.alocacoes),
    )
