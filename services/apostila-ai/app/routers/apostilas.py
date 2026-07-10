"""Rotas de processamento de apostilas, listagem de exercícios e geração de
prova a partir do material já processado.
"""
from __future__ import annotations

import logging
import time

import os

from fastapi import APIRouter, BackgroundTasks, Depends, HTTPException, Query
from fastapi.responses import FileResponse
from sqlalchemy.orm import Session

from app.auth import require_internal_api_key
from app.config import get_settings
from app.database import SessionLocal, get_db
from app.models.db_models import (
    ApostilaIA,
    ApostilaIAChunk,
    ApostilaIAExercicio,
    ApostilaIAPagina,
    ApostilaStatus,
)
from app.models.schemas import (
    ContextoApostilaRequest,
    ContextoApostilaResponse,
    ExercicioResponse,
    GerarProvaRequest,
    GerarProvaResponse,
    ProcessarApostilaRequest,
    ProcessarApostilaResponse,
    QuestaoProva,
)
from app.services.chunk_service import montar_chunks
from app.services.apostila_cleanup_service import limpar_dados_derivados_apostila
from app.services.exercise_service import processar_exercicios_da_apostila
from app.services.sugestoes_service import montar_sugestoes_chat
from app.services.openai_service import (
    adicionar_textos_ao_vector_store_em_lote,
    criar_vector_store,
    extrair_numero_pagina_do_filename,
    redigir_slides_markdown,
    responder_com_file_search,
)
from app.services.pdf_service import (
    PdfValidationError,
    extrair_texto_por_pagina,
    gerar_capa_apostila,
    validar_pdf_path,
)

logger = logging.getLogger("apostila_ai.routers.apostilas")

router = APIRouter(
    prefix="/apostilas", tags=["apostilas"], dependencies=[Depends(require_internal_api_key)]
)


def _get_or_create_apostila(db: Session, apostila_id: int, titulo: str, pdf_path: str) -> ApostilaIA:
    apostila = db.get(ApostilaIA, apostila_id)
    if apostila is None:
        apostila = ApostilaIA(
            id=apostila_id,
            titulo=titulo,
            arquivo_pdf=pdf_path,
            status=ApostilaStatus.pendente,
        )
        db.add(apostila)
        db.flush()
    else:
        apostila.titulo = titulo
        apostila.arquivo_pdf = pdf_path
    return apostila


def _processar_apostila_background(apostila_id: int, pdf_path: str, titulo: str) -> None:
    """Executa o processamento pesado (extração + chunking + exercícios +
    vector store) fora do request HTTP. Roda em thread separada via
    BackgroundTasks (Starlette usa run_in_threadpool para callables síncronos),
    então não bloqueia o event loop nem outras requisições enquanto processa
    PDFs grandes (pode levar muitos minutos para centenas de páginas).
    Usa sua própria sessão de banco, já que a sessão do request original é
    fechada antes desta função rodar.
    """
    settings = get_settings()
    start_time = time.monotonic()
    log_ctx = {"apostila_id": apostila_id}

    def _marcar_erro(exc: Exception) -> None:
        # Sessão nova e isolada — se a conexão original caiu (ex.: timeout de
        # inatividade durante chamadas longas à OpenAI), esta ainda funciona.
        db_erro = SessionLocal()
        try:
            apostila = db_erro.get(ApostilaIA, apostila_id)
            if apostila is not None:
                apostila.status = ApostilaStatus.erro
                apostila.erro = str(exc)
                db_erro.commit()
        except Exception:
            logger.exception("falha_ao_marcar_erro_apostila %s", log_ctx)
        finally:
            db_erro.close()

    try:
        # Sessão 1 (curta): cria/atualiza o registro, limpa dados anteriores
        # (reprocessamento) e marca como "processando".
        db = SessionLocal()
        try:
            apostila = _get_or_create_apostila(db, apostila_id, titulo, pdf_path)
            limpar_dados_derivados_apostila(db, apostila_id, settings.pages_path)
            apostila = db.get(ApostilaIA, apostila_id)
            apostila.status = ApostilaStatus.processando
            apostila.erro = None
            db.commit()
        finally:
            db.close()

        try:
            abs_pdf_path = validar_pdf_path(pdf_path, base_dir=settings.uploads_path)
        except PdfValidationError:
            # Caminho pode estar fora do diretório padrão de uploads (ex: outro
            # storage configurado pelo PHP) — ainda validamos magic bytes/tamanho
            # mas sem restringir a um base_dir fixo nesse fallback.
            abs_pdf_path = validar_pdf_path(pdf_path, base_dir=None)

        paginas = extrair_texto_por_pagina(
            abs_pdf_path, apostila_id=apostila_id, pages_dir=settings.pages_path
        )
        gerar_capa_apostila(abs_pdf_path, apostila_id, settings.pages_path)
        paginas_sem_texto = sum(1 for p in paginas if p.possivel_escaneada)
        chunks = montar_chunks(apostila_id, paginas)

        # Sessão 2 (curta): persiste páginas e chunks — nenhuma chamada externa
        # acontece entre as escritas, então a conexão não fica ociosa.
        db = SessionLocal()
        try:
            for pagina in paginas:
                db.add(
                    ApostilaIAPagina(
                        apostila_id=apostila_id,
                        numero_pagina=pagina.numero_pagina,
                        texto_extraido=pagina.texto,
                        imagem_path=pagina.imagem_path,
                    )
                )
            for chunk in chunks:
                db.add(
                    ApostilaIAChunk(
                        apostila_id=apostila_id,
                        pagina_inicio=chunk.pagina_inicio,
                        pagina_fim=chunk.pagina_fim,
                        conteudo=chunk.conteudo,
                        metadata_json=chunk.metadata_json,
                        embedding_provider="openai",
                    )
                )
            db.commit()
        finally:
            db.close()

        # Detecção de exercícios: cada página abre/fecha sua própria sessão
        # internamente (ver exercise_service.py) — etapa potencialmente longa
        # (1 chamada OpenAI por página), por isso nenhuma sessão fica aberta
        # ociosa durante todo o loop.
        total_exercicios = processar_exercicios_da_apostila(SessionLocal, apostila_id, paginas)

        # Vector store / file search: só chamadas OpenAI, sem uso do banco.
        # Upload em lote (1 chamada, concorrência interna) em vez de subir e
        # esperar a indexação de cada página individualmente.
        vector_store_id = criar_vector_store(f"apostila_{apostila_id}")
        adicionar_textos_ao_vector_store_em_lote(
            vector_store_id,
            [(p.numero_pagina, p.texto) for p in paginas if p.texto],
        )

        # Sessão 3 (curta): grava resultado final e sugestões dinâmicas de chat.
        db = SessionLocal()
        try:
            apostila = db.get(ApostilaIA, apostila_id)
            apostila.vector_store_id = vector_store_id
            apostila.total_paginas = len(paginas)
            apostila.status = ApostilaStatus.pronto
            apostila.erro = None
            apostila.sugestoes_chat = montar_sugestoes_chat(db, apostila_id)
            db.commit()
        finally:
            db.close()

        elapsed = time.monotonic() - start_time
        logger.info(
            "apostila_processada_com_sucesso apostila_id=%s total_paginas=%s "
            "paginas_sem_texto=%s chunks=%s exercicios=%s tempo_segundos=%.2f",
            apostila_id,
            len(paginas),
            paginas_sem_texto,
            len(chunks),
            total_exercicios,
            elapsed,
        )

    except Exception as exc:
        _marcar_erro(exc)

        elapsed = time.monotonic() - start_time
        logger.error(
            "erro_processamento_apostila %s tipo_erro=%s tempo_segundos=%.2f",
            log_ctx,
            type(exc).__name__,
            elapsed,
        )


@router.post("/{apostila_id}/processar", response_model=ProcessarApostilaResponse)
async def processar_apostila(
    apostila_id: int,
    payload: ProcessarApostilaRequest,
    background_tasks: BackgroundTasks,
    db: Session = Depends(get_db),
) -> ProcessarApostilaResponse:
    """Dispara o processamento em background e responde imediatamente com
    status "processando". O lado PHP deve fazer polling em
    GET /admin/apostilas-ia/{id}/status para acompanhar o progresso —
    chamar este endpoint não bloqueia mais aguardando o PDF inteiro processar.
    """
    apostila = _get_or_create_apostila(db, apostila_id, payload.titulo, payload.pdf_path)
    apostila.status = ApostilaStatus.processando
    apostila.erro = None
    db.commit()

    background_tasks.add_task(
        _processar_apostila_background, apostila_id, payload.pdf_path, payload.titulo
    )

    return ProcessarApostilaResponse(apostila_id=apostila_id, status="processando")


@router.get("/{apostila_id}/capa")
async def capa_da_apostila(apostila_id: int):
    """Serve a capa da apostila (página 1 renderizada inteira) — usada na
    galeria "Meu Material". Caminho é por convenção (pages_dir/{id}/capa.png),
    sem precisar de coluna própria no banco.
    """
    settings = get_settings()
    caminho = os.path.join(settings.pages_path, str(apostila_id), "capa.png")
    if not os.path.isfile(caminho):
        raise HTTPException(status_code=404, detail={"error": "Capa não encontrada"})
    return FileResponse(caminho)


@router.get("/{apostila_id}/pdf")
async def pdf_da_apostila(apostila_id: int, db: Session = Depends(get_db)):
    """Serve o PDF original da apostila. O lado PHP atua como proxy autenticado
    (igual à capa/imagem), evitando depender de um volume de uploads
    compartilhado entre os containers PHP e Python.
    """
    apostila = db.get(ApostilaIA, apostila_id)
    if apostila is None or not apostila.arquivo_pdf:
        raise HTTPException(status_code=404, detail={"error": "Apostila não encontrada"})

    settings = get_settings()
    try:
        caminho = validar_pdf_path(apostila.arquivo_pdf, base_dir=settings.uploads_path)
    except PdfValidationError:
        # Caminho pode estar fora do diretório padrão de uploads (mesmo fallback
        # usado no processamento) — ainda valida magic bytes/tamanho.
        try:
            caminho = validar_pdf_path(apostila.arquivo_pdf, base_dir=None)
        except PdfValidationError:
            raise HTTPException(status_code=404, detail={"error": "PDF não encontrado"})

    return FileResponse(
        caminho, media_type="application/pdf", filename=os.path.basename(caminho)
    )


@router.get("/{apostila_id}/paginas/{numero_pagina}/imagem")
async def imagem_da_pagina(
    apostila_id: int, numero_pagina: int, db: Session = Depends(get_db)
):
    """Serve a maior imagem embutida extraída desta página (figura/diagrama
    associado a um exercício ou explicação), se houver. O lado PHP atua como
    proxy autenticado entre o navegador do professor e este endpoint — só o
    backend conhece a X-Internal-Api-Key.
    """
    pagina = (
        db.query(ApostilaIAPagina)
        .filter(
            ApostilaIAPagina.apostila_id == apostila_id,
            ApostilaIAPagina.numero_pagina == numero_pagina,
        )
        .first()
    )
    if pagina is None or not pagina.imagem_path or not os.path.isfile(pagina.imagem_path):
        raise HTTPException(status_code=404, detail={"error": "Imagem não encontrada"})

    return FileResponse(pagina.imagem_path)


def _paginas_com_imagem(db: Session, apostila_id: int, numeros_pagina: set[int]) -> set[int]:
    """Dado um conjunto de números de página, retorna o subconjunto que tem
    imagem extraída (apostila_ia_paginas.imagem_path preenchido)."""
    if not numeros_pagina:
        return set()
    linhas = (
        db.query(ApostilaIAPagina.numero_pagina)
        .filter(
            ApostilaIAPagina.apostila_id == apostila_id,
            ApostilaIAPagina.numero_pagina.in_(numeros_pagina),
            ApostilaIAPagina.imagem_path.isnot(None),
        )
        .all()
    )
    return {linha[0] for linha in linhas}


@router.get("/{apostila_id}/exercicios", response_model=list[ExercicioResponse])
async def listar_exercicios(
    apostila_id: int,
    tema: str | None = Query(default=None),
    pagina: int | None = Query(default=None),
    tipo: str | None = Query(default=None),
    db: Session = Depends(get_db),
) -> list[ExercicioResponse]:
    query = db.query(ApostilaIAExercicio).filter(
        ApostilaIAExercicio.apostila_id == apostila_id
    )

    if tema:
        query = query.filter(ApostilaIAExercicio.tema.ilike(f"%{tema}%"))
    if pagina is not None:
        query = query.filter(ApostilaIAExercicio.pagina == pagina)
    if tipo:
        query = query.filter(ApostilaIAExercicio.tipo == tipo)

    exercicios = query.order_by(ApostilaIAExercicio.pagina.asc()).all()
    paginas_com_imagem = _paginas_com_imagem(
        db, apostila_id, {e.pagina for e in exercicios}
    )

    respostas = []
    for e in exercicios:
        resposta = ExercicioResponse.model_validate(e)
        resposta.tem_imagem = e.pagina in paginas_com_imagem
        respostas.append(resposta)
    return respostas


@router.post("/{apostila_id}/gerar-prova", response_model=GerarProvaResponse)
async def gerar_prova(
    apostila_id: int,
    payload: GerarProvaRequest,
    db: Session = Depends(get_db),
) -> GerarProvaResponse:
    query = db.query(ApostilaIAExercicio).filter(
        ApostilaIAExercicio.apostila_id == apostila_id
    )

    if payload.tema:
        query = query.filter(ApostilaIAExercicio.tema.ilike(f"%{payload.tema}%"))
    if payload.nivel:
        query = query.filter(ApostilaIAExercicio.dificuldade == payload.nivel)

    candidatos = query.order_by(ApostilaIAExercicio.pagina.asc()).all()
    selecionados = candidatos[: payload.quantidade]

    nota = None
    if len(selecionados) < payload.quantidade:
        nota = (
            f"Apenas {len(selecionados)} exercício(s) disponível(is) na apostila "
            f"para os critérios informados (solicitado: {payload.quantidade})."
        )

    paginas_com_imagem = _paginas_com_imagem(
        db, apostila_id, {ex.pagina for ex in selecionados}
    )

    questoes = [
        QuestaoProva(
            id=ex.id,
            pagina=ex.pagina,
            tema=ex.tema,
            tipo=ex.tipo.value if hasattr(ex.tipo, "value") else str(ex.tipo),
            enunciado=ex.enunciado,
            alternativas=ex.alternativas,
            gabarito=ex.gabarito if payload.incluir_gabarito else None,
            dificuldade=ex.dificuldade.value
            if hasattr(ex.dificuldade, "value")
            else str(ex.dificuldade),
            tem_imagem=ex.pagina in paginas_com_imagem,
        )
        for ex in selecionados
    ]

    return GerarProvaResponse(
        apostila_id=apostila_id,
        quantidade_solicitada=payload.quantidade,
        quantidade_retornada=len(questoes),
        questoes=questoes,
        nota=nota,
    )


@router.post("/{apostila_id}/contexto", response_model=ContextoApostilaResponse)
async def buscar_contexto(
    apostila_id: int,
    payload: ContextoApostilaRequest,
    db: Session = Depends(get_db),
) -> ContextoApostilaResponse:
    """Busca o conteúdo da apostila relevante para um capítulo/tema via
    file_search (RAG) e organiza esse conteúdo em um roteiro de slides limpo
    (Markdown). Não chama nenhum serviço externo de geração de slides — isso
    é feito do lado PHP, que já tem uma integração própria com o Gamma
    (Integrations/SlidesController). Esta rota só garante que o `conteudo`
    enviado ao Gamma seja realmente baseado no material da apostila.
    """
    apostila = db.get(ApostilaIA, apostila_id)
    if apostila is None:
        raise HTTPException(status_code=404, detail={"error": "Apostila não encontrada"})

    if not apostila.vector_store_id:
        raise HTTPException(
            status_code=409,
            detail={"error": "Apostila ainda não possui índice de busca pronto"},
        )

    pergunta_busca = (
        f"Apresente o conteúdo sobre: {payload.capitulo_ou_tema}. "
        "Liste os principais conceitos, definições e pontos importantes "
        "encontrados na apostila sobre esse tema, com o máximo de detalhe possível."
    )
    contexto_bruto, citacoes = responder_com_file_search(apostila.vector_store_id, pergunta_busca)

    paginas_usadas: list[int] = []
    for citacao in citacoes:
        numero_pagina = extrair_numero_pagina_do_filename(citacao.get("filename"))
        if numero_pagina is not None and numero_pagina not in paginas_usadas:
            paginas_usadas.append(numero_pagina)
    paginas_usadas.sort()

    conteudo_sugerido = redigir_slides_markdown(
        contexto=contexto_bruto,
        capitulo_ou_tema=payload.capitulo_ou_tema,
        instrucoes=None,
        num_slides=payload.num_slides,
    )

    return ContextoApostilaResponse(
        conteudo_sugerido=conteudo_sugerido, paginas_usadas=paginas_usadas
    )
