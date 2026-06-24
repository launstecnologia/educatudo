"""Rota de chat/RAG sobre o conteúdo de uma apostila."""
from __future__ import annotations

import logging

from fastapi import APIRouter, Depends, HTTPException
from sqlalchemy.orm import Session

from app.auth import require_internal_api_key
from app.database import get_db
from app.models.schemas import ChatApostilaRequest, ChatApostilaResponse
from app.services.rag_service import (
    ApostilaNaoEncontradaError,
    ApostilaSemVectorStoreError,
    responder_pergunta_sobre_apostila,
)

logger = logging.getLogger("apostila_ai.routers.chat")

router = APIRouter(
    prefix="/chat", tags=["chat"], dependencies=[Depends(require_internal_api_key)]
)


@router.post("/apostila", response_model=ChatApostilaResponse)
async def chat_apostila(
    payload: ChatApostilaRequest, db: Session = Depends(get_db)
) -> ChatApostilaResponse:
    try:
        resultado = responder_pergunta_sobre_apostila(
            db, payload.apostila_id, payload.professor_id, payload.pergunta
        )
    except ApostilaNaoEncontradaError as exc:
        raise HTTPException(status_code=404, detail={"error": str(exc)}) from exc
    except ApostilaSemVectorStoreError as exc:
        raise HTTPException(status_code=409, detail={"error": str(exc)}) from exc
    except Exception as exc:
        logger.exception(
            "erro_chat_apostila apostila_id=%s", payload.apostila_id
        )
        raise HTTPException(
            status_code=502, detail={"error": "falha ao consultar IA da apostila"}
        ) from exc

    return ChatApostilaResponse(**resultado)
