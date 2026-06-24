"""Ponto de entrada FastAPI do serviço apostila-ai (IA da Apostila)."""
from __future__ import annotations

import logging

from fastapi import FastAPI
from fastapi.responses import JSONResponse

from app.config import get_settings
from app.database import Base, engine
from app.routers import apostilas, chat, health

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(name)s %(message)s",
)

settings = get_settings()

app = FastAPI(
    title="Apostila AI",
    description="IA da Apostila — processamento, RAG e geração de provas para EducaTudo",
    version="1.0.0",
)


@app.on_event("startup")
async def on_startup() -> None:
    # Cria as tabelas apenas se ainda não existirem (não substitui migrations
    # do lado PHP — útil em dev local para inicializar rapidamente).
    Base.metadata.create_all(bind=engine)


@app.exception_handler(Exception)
async def unhandled_exception_handler(request, exc):  # noqa: ANN001
    logging.getLogger("apostila_ai.main").exception(
        "erro_nao_tratado path=%s tipo_erro=%s", request.url.path, type(exc).__name__
    )
    return JSONResponse(status_code=500, content={"error": "internal_server_error"})


app.include_router(health.router)
app.include_router(apostilas.router)
app.include_router(chat.router)
