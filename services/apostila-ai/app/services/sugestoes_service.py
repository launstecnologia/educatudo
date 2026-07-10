"""Sugestões dinâmicas de perguntas para o chat, baseadas no conteúdo processado."""
from __future__ import annotations

from sqlalchemy.orm import Session

from app.models.db_models import ApostilaIAExercicio


def montar_sugestoes_chat(db: Session, apostila_id: int) -> list[str]:
    """Monta sugestões a partir de temas/capítulos detectados nos exercícios."""
    sugestoes: list[str] = [
        "Resuma esta apostila",
        "Liste os exercícios encontrados",
    ]

    temas_rows = (
        db.query(ApostilaIAExercicio.tema)
        .filter(
            ApostilaIAExercicio.apostila_id == apostila_id,
            ApostilaIAExercicio.tema.isnot(None),
            ApostilaIAExercicio.tema != "",
        )
        .distinct()
        .limit(6)
        .all()
    )
    for (tema,) in temas_rows:
        tema_limpo = (tema or "").strip()
        if not tema_limpo:
            continue
        sugestao = f"Explique o tema: {tema_limpo}"
        if sugestao not in sugestoes:
            sugestoes.append(sugestao)
        if len(sugestoes) >= 6:
            break

    capitulos_rows = (
        db.query(ApostilaIAExercicio.capitulo)
        .filter(
            ApostilaIAExercicio.apostila_id == apostila_id,
            ApostilaIAExercicio.capitulo.isnot(None),
            ApostilaIAExercicio.capitulo != "",
        )
        .distinct()
        .limit(4)
        .all()
    )
    for (capitulo,) in capitulos_rows:
        capitulo_limpo = (capitulo or "").strip()
        if not capitulo_limpo:
            continue
        sugestao = f"O que a apostila diz sobre {capitulo_limpo}?"
        if sugestao not in sugestoes:
            sugestoes.append(sugestao)
        if len(sugestoes) >= 8:
            break

    if len(sugestoes) < 8:
        sugestoes.append("Quais páginas falam sobre este assunto?")
    if len(sugestoes) < 8:
        sugestoes.append("Explique este conteúdo de forma simples")

    return sugestoes[:8]
