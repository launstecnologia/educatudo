"""Orquestra o fluxo de chat/RAG: consulta o vector store da apostila via
file_search, registra a conversa, e extrai as páginas citadas.
"""
from __future__ import annotations

import logging

from sqlalchemy.orm import Session

from app.models.db_models import ApostilaIA, ApostilaIAConversa, ApostilaIAPagina
from app.services.openai_service import (
    extrair_numero_pagina_do_filename,
    responder_com_file_search,
)

logger = logging.getLogger("apostila_ai.rag_service")


class ApostilaSemVectorStoreError(Exception):
    pass


class ApostilaNaoEncontradaError(Exception):
    pass


HISTORICO_MAX_MENSAGENS = 6


def _buscar_historico_recente(
    db: Session, apostila_id: int, professor_id: int | None
) -> list[tuple[str, str]]:
    """Retorna as últimas mensagens (pergunta, resposta) desta conversa, em
    ordem cronológica, para dar contexto de continuidade ao modelo (ex.:
    "pode gerar" referindo-se ao que foi discutido na mensagem anterior).
    """
    query = db.query(ApostilaIAConversa).filter(
        ApostilaIAConversa.apostila_id == apostila_id
    )
    if professor_id is not None:
        query = query.filter(ApostilaIAConversa.professor_id == professor_id)

    recentes = (
        query.order_by(ApostilaIAConversa.id.desc())
        .limit(HISTORICO_MAX_MENSAGENS)
        .all()
    )
    recentes.reverse()
    return [(c.pergunta, c.resposta) for c in recentes]


def responder_pergunta_sobre_apostila(
    db: Session, apostila_id: int, professor_id: int | None, pergunta: str
) -> dict:
    apostila = db.get(ApostilaIA, apostila_id)
    if apostila is None:
        raise ApostilaNaoEncontradaError(f"Apostila {apostila_id} não encontrada")

    if not apostila.vector_store_id:
        raise ApostilaSemVectorStoreError(
            "Apostila ainda não possui índice de busca (vector store) pronto"
        )

    historico = _buscar_historico_recente(db, apostila_id, professor_id)

    resposta_texto, citacoes = responder_com_file_search(
        apostila.vector_store_id, pergunta, historico=historico
    )

    paginas_usadas: list[int] = []
    fontes: list[dict] = []
    for citacao in citacoes:
        numero_pagina = extrair_numero_pagina_do_filename(citacao.get("filename"))
        if numero_pagina is not None and numero_pagina not in paginas_usadas:
            paginas_usadas.append(numero_pagina)
            fontes.append({"pagina": numero_pagina, "trecho": citacao.get("texto", "")})

    paginas_usadas.sort()

    paginas_com_imagem: list[int] = []
    if paginas_usadas:
        paginas_com_imagem_rows = (
            db.query(ApostilaIAPagina.numero_pagina)
            .filter(
                ApostilaIAPagina.apostila_id == apostila_id,
                ApostilaIAPagina.numero_pagina.in_(paginas_usadas),
                ApostilaIAPagina.imagem_path.isnot(None),
            )
            .all()
        )
        paginas_com_imagem = sorted(row[0] for row in paginas_com_imagem_rows)

    conversa = ApostilaIAConversa(
        apostila_id=apostila_id,
        professor_id=professor_id,
        pergunta=pergunta,
        resposta=resposta_texto,
        paginas_usadas=paginas_usadas,
    )
    db.add(conversa)
    db.commit()

    return {
        "resposta": resposta_texto,
        "paginas_usadas": paginas_usadas,
        "paginas_com_imagem": paginas_com_imagem,
        "fontes": fontes,
    }
