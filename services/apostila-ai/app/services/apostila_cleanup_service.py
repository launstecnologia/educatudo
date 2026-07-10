"""Limpeza de dados derivados de uma apostila antes de reprocessamento."""
from __future__ import annotations

import logging
import os
import shutil

from sqlalchemy.orm import Session

from app.models.db_models import (
    ApostilaIA,
    ApostilaIAChunk,
    ApostilaIAExercicio,
    ApostilaIAPagina,
)
from app.services.openai_service import remover_vector_store

logger = logging.getLogger("apostila_ai.apostila_cleanup_service")


def limpar_diretorio_paginas(apostila_id: int, pages_dir: str) -> None:
    """Remove imagens/capa extraídas anteriormente desta apostila."""
    dest_dir = os.path.join(pages_dir, str(apostila_id))
    if os.path.isdir(dest_dir):
        shutil.rmtree(dest_dir, ignore_errors=True)


def limpar_dados_derivados_apostila(
    db: Session, apostila_id: int, pages_dir: str
) -> str | None:
    """Apaga páginas/chunks/exercícios anteriores e prepara remoção do vector
    store OpenAI antigo. Retorna o ID do vector store removido (se havia).
    """
    apostila = db.get(ApostilaIA, apostila_id)
    vector_store_id_antigo = apostila.vector_store_id if apostila else None

    db.query(ApostilaIAPagina).filter(
        ApostilaIAPagina.apostila_id == apostila_id
    ).delete(synchronize_session=False)
    db.query(ApostilaIAChunk).filter(
        ApostilaIAChunk.apostila_id == apostila_id
    ).delete(synchronize_session=False)
    db.query(ApostilaIAExercicio).filter(
        ApostilaIAExercicio.apostila_id == apostila_id
    ).delete(synchronize_session=False)

    if apostila is not None:
        apostila.vector_store_id = None
        apostila.total_paginas = 0

    db.commit()

    if vector_store_id_antigo:
        try:
            remover_vector_store(vector_store_id_antigo)
        except Exception:
            logger.warning(
                "falha_remover_vector_store_antigo apostila_id=%s vector_store_id=%s",
                apostila_id,
                vector_store_id_antigo,
                exc_info=True,
            )

    limpar_diretorio_paginas(apostila_id, pages_dir)
    return vector_store_id_antigo
