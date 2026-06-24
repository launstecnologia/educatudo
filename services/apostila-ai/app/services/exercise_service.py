"""Detecção de exercícios por página via OpenAI, com persistência em
`apostila_ia_exercicios`.
"""
from __future__ import annotations

import logging
from collections.abc import Callable

from sqlalchemy.orm import Session

from app.models.db_models import (
    ApostilaIAExercicio,
    ExercicioDificuldade,
    ExercicioTipo,
)
from app.services.openai_service import detectar_exercicios
from app.services.pdf_service import PageText

logger = logging.getLogger("apostila_ai.exercise_service")

VALID_TIPOS = {t.value for t in ExercicioTipo}
VALID_DIFICULDADES = {d.value for d in ExercicioDificuldade}


def _coerce_tipo(valor: object) -> ExercicioTipo:
    if isinstance(valor, str) and valor in VALID_TIPOS:
        return ExercicioTipo(valor)
    return ExercicioTipo.outro


def _coerce_dificuldade(valor: object) -> ExercicioDificuldade:
    if isinstance(valor, str) and valor in VALID_DIFICULDADES:
        return ExercicioDificuldade(valor)
    return ExercicioDificuldade.nao_identificada


def processar_exercicios_da_apostila(
    session_factory: Callable[[], Session], apostila_id: int, paginas: list[PageText]
) -> int:
    """Para cada página com texto, chama a detecção de exercícios via OpenAI
    e persiste os resultados. Retorna a quantidade total de exercícios
    salvos. Páginas sem texto extraível são puladas (nada para analisar).

    Para apostilas grandes (centenas de páginas), cada chamada à OpenAI pode
    levar segundos — se tudo ficasse em uma única transação/sessão aberta por
    todo o loop, a conexão com o banco (remoto, fora deste host) pode cair por
    inatividade no meio do processamento (erro real observado: "Lost
    connection to MySQL server during query" após ~12 minutos). Por isso,
    cada página usa sua própria sessão de banco, aberta e fechada rapidamente
    logo após a chamada à OpenAI — a conexão nunca fica ociosa por muito tempo.
    """
    total_salvos = 0

    for pagina in paginas:
        if not pagina.texto:
            continue

        try:
            exercicios_brutos = detectar_exercicios(pagina.texto, pagina.numero_pagina)
        except Exception:
            logger.exception(
                "erro_detectar_exercicios apostila_id=%s pagina=%s",
                apostila_id,
                pagina.numero_pagina,
            )
            continue

        exercicios_da_pagina = []
        for item in exercicios_brutos:
            if not isinstance(item, dict):
                continue
            enunciado = (item.get("enunciado") or "").strip()
            if not enunciado:
                continue

            exercicios_da_pagina.append(
                ApostilaIAExercicio(
                    apostila_id=apostila_id,
                    pagina=item.get("pagina") or pagina.numero_pagina,
                    capitulo=item.get("capitulo"),
                    tema=item.get("tema"),
                    tipo=_coerce_tipo(item.get("tipo")),
                    enunciado=enunciado,
                    alternativas=item.get("alternativas") or None,
                    gabarito=item.get("gabarito"),
                    dificuldade=_coerce_dificuldade(item.get("dificuldade")),
                )
            )

        if not exercicios_da_pagina:
            continue

        db_pagina = session_factory()
        try:
            db_pagina.add_all(exercicios_da_pagina)
            db_pagina.commit()
            total_salvos += len(exercicios_da_pagina)
        except Exception:
            db_pagina.rollback()
            logger.exception(
                "erro_salvar_exercicios apostila_id=%s pagina=%s",
                apostila_id,
                pagina.numero_pagina,
            )
        finally:
            db_pagina.close()

    return total_salvos
