"""Orquestra o fluxo de chat/RAG: consulta o vector store da apostila via
file_search, registra a conversa por sessão, e extrai as páginas citadas.
"""
from __future__ import annotations

import json
import logging
from collections.abc import Iterator
from datetime import datetime, timezone

from sqlalchemy.orm import Session

from app.models.db_models import (
    ApostilaIA,
    ApostilaIAConversa,
    ApostilaIAPagina,
    ApostilaIASessao,
)
from app.services.openai_service import (
    extrair_numero_pagina_do_filename,
    gerar_resumo_conversa_sessao,
    iterar_resposta_com_file_search,
    responder_com_file_search,
)

logger = logging.getLogger("apostila_ai.rag_service")


class ApostilaSemVectorStoreError(Exception):
    pass


class ApostilaNaoEncontradaError(Exception):
    pass


class SessaoNaoEncontradaError(Exception):
    pass


HISTORICO_MAX_MENSAGENS = 12
RESUMO_INTERVALO_MENSAGENS = 6


def _buscar_sessao(db: Session, sessao_id: int, apostila_id: int) -> ApostilaIASessao:
    sessao = db.get(ApostilaIASessao, sessao_id)
    if sessao is None or sessao.apostila_id != apostila_id:
        raise SessaoNaoEncontradaError(
            f"Sessão {sessao_id} não encontrada para apostila {apostila_id}"
        )
    return sessao


def _buscar_historico_recente(
    db: Session, sessao_id: int | None, apostila_id: int, professor_id: int | None
) -> list[tuple[str, str]]:
    query = db.query(ApostilaIAConversa).filter(
        ApostilaIAConversa.apostila_id == apostila_id
    )
    if sessao_id is not None:
        query = query.filter(ApostilaIAConversa.sessao_id == sessao_id)
    elif professor_id is not None:
        query = query.filter(ApostilaIAConversa.professor_id == professor_id)

    recentes = (
        query.order_by(ApostilaIAConversa.id.desc())
        .limit(HISTORICO_MAX_MENSAGENS)
        .all()
    )
    recentes.reverse()
    return [(c.pergunta, c.resposta) for c in recentes]


def _montar_metadados_resposta(
    db: Session, apostila_id: int, citacoes: list[dict]
) -> dict:
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

    return {
        "paginas_usadas": paginas_usadas,
        "paginas_com_imagem": paginas_com_imagem,
        "fontes": fontes,
    }


def _persistir_conversa(
    db: Session,
    apostila_id: int,
    professor_id: int | None,
    sessao_id: int | None,
    pergunta: str,
    resposta_texto: str,
    paginas_usadas: list[int],
) -> ApostilaIAConversa:
    conversa = ApostilaIAConversa(
        apostila_id=apostila_id,
        professor_id=professor_id,
        sessao_id=sessao_id,
        pergunta=pergunta,
        resposta=resposta_texto,
        paginas_usadas=paginas_usadas,
    )
    db.add(conversa)
    if sessao_id is not None:
        _tocar_sessao_atualizada(db, sessao_id)
    db.commit()
    db.refresh(conversa)
    return conversa


def _tocar_sessao_atualizada(db: Session, sessao_id: int) -> None:
    sessao = db.get(ApostilaIASessao, sessao_id)
    if sessao is None:
        return
    sessao.updated_at = datetime.now(timezone.utc)


def _atualizar_resumo_sessao_se_necessario(db: Session, sessao_id: int) -> None:
    total = (
        db.query(ApostilaIAConversa)
        .filter(ApostilaIAConversa.sessao_id == sessao_id)
        .count()
    )
    if total == 0 or total % RESUMO_INTERVALO_MENSAGENS != 0:
        return

    conversas = (
        db.query(ApostilaIAConversa)
        .filter(ApostilaIAConversa.sessao_id == sessao_id)
        .order_by(ApostilaIAConversa.id.asc())
        .all()
    )
    mensagens = [(c.pergunta, c.resposta) for c in conversas]
    try:
        resumo = gerar_resumo_conversa_sessao(mensagens)
    except Exception:
        logger.exception("erro_gerar_resumo_sessao sessao_id=%s", sessao_id)
        return

    sessao = db.get(ApostilaIASessao, sessao_id)
    if sessao is None:
        return
    sessao.resumo = resumo
    db.commit()


def _validar_apostila_para_chat(db: Session, apostila_id: int) -> ApostilaIA:
    apostila = db.get(ApostilaIA, apostila_id)
    if apostila is None:
        raise ApostilaNaoEncontradaError(f"Apostila {apostila_id} não encontrada")

    if not apostila.vector_store_id:
        raise ApostilaSemVectorStoreError(
            "Apostila ainda não possui índice de busca (vector store) pronto"
        )
    return apostila


def _contexto_resumo_sessao(db: Session, sessao_id: int | None) -> str | None:
    if sessao_id is None:
        return None
    sessao = db.get(ApostilaIASessao, sessao_id)
    if sessao is None or not sessao.resumo:
        return None
    return sessao.resumo


def responder_pergunta_sobre_apostila(
    db: Session,
    apostila_id: int,
    professor_id: int | None,
    pergunta: str,
    sessao_id: int | None = None,
) -> dict:
    apostila = _validar_apostila_para_chat(db, apostila_id)
    if sessao_id is not None:
        _buscar_sessao(db, sessao_id, apostila_id)

    historico = _buscar_historico_recente(db, sessao_id, apostila_id, professor_id)
    resumo_sessao = _contexto_resumo_sessao(db, sessao_id)

    resposta_texto, citacoes = responder_com_file_search(
        apostila.vector_store_id,
        pergunta,
        historico=historico,
        resumo_sessao=resumo_sessao,
    )

    metadados = _montar_metadados_resposta(db, apostila_id, citacoes)
    conversa = _persistir_conversa(
        db,
        apostila_id,
        professor_id,
        sessao_id,
        pergunta,
        resposta_texto,
        metadados["paginas_usadas"],
    )

    if sessao_id is not None:
        _atualizar_resumo_sessao_se_necessario(db, sessao_id)

    return {
        "resposta": resposta_texto,
        "conversa_id": conversa.id,
        "sessao_id": sessao_id,
        **metadados,
    }


def _formatar_sse(evento: str, dados: dict) -> str:
    return f"event: {evento}\ndata: {json.dumps(dados, ensure_ascii=False)}\n\n"


def stream_responder_pergunta_sobre_apostila(
    db: Session,
    apostila_id: int,
    professor_id: int | None,
    pergunta: str,
    sessao_id: int | None = None,
) -> Iterator[str]:
    """Gera eventos SSE para streaming da resposta do chat."""
    try:
        apostila = _validar_apostila_para_chat(db, apostila_id)
        if sessao_id is not None:
            _buscar_sessao(db, sessao_id, apostila_id)
    except ApostilaNaoEncontradaError as exc:
        yield _formatar_sse("error", {"error": str(exc)})
        return
    except ApostilaSemVectorStoreError as exc:
        yield _formatar_sse("error", {"error": str(exc)})
        return
    except SessaoNaoEncontradaError as exc:
        yield _formatar_sse("error", {"error": str(exc)})
        return

    historico = _buscar_historico_recente(db, sessao_id, apostila_id, professor_id)
    resumo_sessao = _contexto_resumo_sessao(db, sessao_id)

    try:
        for tipo, payload in iterar_resposta_com_file_search(
            apostila.vector_store_id,
            pergunta,
            historico=historico,
            resumo_sessao=resumo_sessao,
        ):
            if tipo == "status":
                yield _formatar_sse("status", {"status": payload})
            elif tipo == "token":
                yield _formatar_sse("token", {"text": payload})
            elif tipo == "concluido":
                resposta_texto = payload["resposta"]
                citacoes = payload["citacoes"]
                metadados = _montar_metadados_resposta(db, apostila_id, citacoes)
                conversa = _persistir_conversa(
                    db,
                    apostila_id,
                    professor_id,
                    sessao_id,
                    pergunta,
                    resposta_texto,
                    metadados["paginas_usadas"],
                )
                if sessao_id is not None:
                    _atualizar_resumo_sessao_se_necessario(db, sessao_id)
                yield _formatar_sse(
                    "done",
                    {
                        "resposta": resposta_texto,
                        "conversa_id": conversa.id,
                        "sessao_id": sessao_id,
                        **metadados,
                    },
                )
    except Exception:
        logger.exception("erro_stream_chat_apostila apostila_id=%s", apostila_id)
        yield _formatar_sse(
            "error", {"error": "falha ao consultar IA da apostila"}
        )
