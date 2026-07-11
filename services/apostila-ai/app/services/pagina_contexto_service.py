"""Detecção de pedidos por página e recuperação direta do texto no banco.

Quando o usuário pede conteúdo de página(s) específica(s), o file_search
semântico pode citar a página certa mas responder com texto de outra —
usamos o texto_extraido da tabela apostila_ia_paginas como fonte autoritativa.
"""
from __future__ import annotations

import re

from sqlalchemy.orm import Session

from app.models.db_models import ApostilaIAPagina

_PAGINA_RE = re.compile(
    r"\b(?:p[aá]g(?:ina|\.)?|pág\.?)\s*(\d{1,4})\b",
    re.IGNORECASE,
)

# Algumas apostilas têm páginas de capa/rosto/crédito antes do início da
# numeração impressa do livro — isso desloca numero_pagina (posição bruta no
# PDF) em relação ao número que aparece de fato no rodapé de cada página
# (ex.: capa + 2 páginas de crédito empurram tudo em +1). O próprio rodapé
# fica gravado como a última linha do texto extraído (ex.: "...\n50"), então
# usamos ele como fonte da verdade em vez de confiar cegamente em
# numero_pagina — autocorrige por apostila, sem precisar saber o deslocamento
# de antemão.
_PAGINA_RODAPE_RE = re.compile(r"\n\s*(\d{1,4})\s*$")
_JANELA_BUSCA_OFFSET = 3


def _extrair_pagina_do_rodape(texto: str) -> int | None:
    match = _PAGINA_RODAPE_RE.search(texto)
    if not match:
        return None
    try:
        return int(match.group(1))
    except ValueError:
        return None


def extrair_paginas_solicitadas(pergunta: str, max_paginas: int = 5) -> list[int]:
    """Extrai números de página explícitos na pergunta (ex.: 'traga a página 50')."""
    if not pergunta or not pergunta.strip():
        return []

    encontradas: list[int] = []
    for match in _PAGINA_RE.finditer(pergunta):
        numero = int(match.group(1))
        if numero <= 0 or numero in encontradas:
            continue
        encontradas.append(numero)
        if len(encontradas) >= max_paginas:
            break

    return encontradas


def buscar_textos_paginas(
    db: Session, apostila_id: int, numeros_pagina: list[int]
) -> dict[int, str]:
    """Retorna {pagina_pedida: texto} para as páginas pedidas.

    Busca numa pequena janela ao redor de cada número pedido (não só o exato)
    porque numero_pagina é a posição bruta no PDF, que pode estar deslocada
    da numeração impressa (ver _extrair_pagina_do_rodape). Prioriza o texto
    cujo rodapé bate exatamente com o número pedido; se nenhum bater (página
    sem rodapé legível, ex.: imagem pura), cai no numero_pagina exato como
    antes.
    """
    if not numeros_pagina:
        return {}

    minimo = max(1, min(numeros_pagina) - _JANELA_BUSCA_OFFSET)
    maximo = max(numeros_pagina) + _JANELA_BUSCA_OFFSET

    rows = (
        db.query(ApostilaIAPagina.numero_pagina, ApostilaIAPagina.texto_extraido)
        .filter(
            ApostilaIAPagina.apostila_id == apostila_id,
            ApostilaIAPagina.numero_pagina.between(minimo, maximo),
        )
        .all()
    )

    por_rodape: dict[int, str] = {}
    por_numero_pagina: dict[int, str] = {}
    for numero_pagina, texto in rows:
        texto_limpo = (texto or "").strip()
        if not texto_limpo:
            continue
        por_numero_pagina[int(numero_pagina)] = texto_limpo
        pagina_rodape = _extrair_pagina_do_rodape(texto_limpo)
        if pagina_rodape is not None and pagina_rodape not in por_rodape:
            por_rodape[pagina_rodape] = texto_limpo

    resultado: dict[int, str] = {}
    for pagina in numeros_pagina:
        if pagina in por_rodape:
            resultado[pagina] = por_rodape[pagina]
        elif pagina in por_numero_pagina:
            resultado[pagina] = por_numero_pagina[pagina]
    return resultado


def montar_bloco_contexto_paginas(paginas_texto: dict[int, str]) -> str:
    """Formata o texto das páginas para injeção no prompt."""
    partes: list[str] = []
    for numero in sorted(paginas_texto.keys()):
        partes.append(f"=== PÁGINA {numero} ===\n\n{paginas_texto[numero]}")
    return "\n\n".join(partes)


def montar_citacoes_paginas(paginas_texto: dict[int, str]) -> list[dict]:
    return [
        {
            "filename": f"pagina_{numero:04d}.txt",
            "texto": texto[:500],
        }
        for numero, texto in sorted(paginas_texto.items())
    ]
