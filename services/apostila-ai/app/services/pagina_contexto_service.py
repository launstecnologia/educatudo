"""Detecção de pedidos por página e recuperação direta do conteúdo no banco.

Quando o usuário pede conteúdo de página(s) específica(s), o file_search
semântico pode citar a página certa mas responder com texto de outra —
usamos o texto_extraido (ou, na ausência dele, a imagem da página) da
tabela apostila_ia_paginas como fonte autoritativa.
"""
from __future__ import annotations

import difflib
import re
from dataclasses import dataclass

from sqlalchemy.orm import Session

from app.models.db_models import ApostilaIAPagina


@dataclass
class PaginaResolvida:
    """Conteúdo real de uma página, já resolvido para o número impresso
    pedido pelo usuário (não a posição bruta no PDF — ver
    resolver_paginas_solicitadas)."""

    numero_pagina_raw: int
    texto: str | None
    imagem_path: str | None

    @property
    def tem_texto(self) -> bool:
        return bool(self.texto and self.texto.strip())

    @property
    def tem_imagem(self) -> bool:
        return bool(self.imagem_path)

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


_PALAVRAS_PAGINA_REFERENCIA = ("pagina", "página", "pág", "pag")
_SIMILARIDADE_MINIMA_TYPO = 0.8


def _parece_palavra_pagina(palavra: str) -> bool:
    """Tolera erro de digitação comum (ex.: "paina", "pagna" em vez de
    "página") comparando a palavra digitada com as referências por
    similaridade de string, em vez de exigir bater com o regex exato."""
    normalizada = palavra.lower().strip(".,;:!?")
    if not normalizada:
        return False
    return any(
        difflib.SequenceMatcher(None, normalizada, referencia).ratio()
        >= _SIMILARIDADE_MINIMA_TYPO
        for referencia in _PALAVRAS_PAGINA_REFERENCIA
    )


def extrair_paginas_solicitadas(pergunta: str, max_paginas: int = 5) -> list[int]:
    """Extrai números de página citados na pergunta (ex.: 'traga a página 50').

    Tenta primeiro o padrão exato ("página"/"pág"/"pag" + número). Se não
    achar nada, tenta um fallback tolerante a erro de digitação: qualquer
    palavra parecida com "página" seguida de um número nas próximas duas
    palavras (ex.: "resume paina 50", "traz a pagna 12 pra mim").
    """
    if not pergunta or not pergunta.strip():
        return []

    encontradas: list[int] = []
    for match in _PAGINA_RE.finditer(pergunta):
        numero = int(match.group(1))
        if numero <= 0 or numero in encontradas:
            continue
        encontradas.append(numero)
        if len(encontradas) >= max_paginas:
            return encontradas

    if encontradas:
        return encontradas

    tokens = pergunta.split()
    for i, token in enumerate(tokens):
        if not _parece_palavra_pagina(token):
            continue
        for proximo in tokens[i + 1 : i + 3]:
            numero_str = proximo.strip(".,;:!?º°")
            if numero_str.isdigit():
                numero = int(numero_str)
                if numero > 0 and numero not in encontradas:
                    encontradas.append(numero)
                break
        if len(encontradas) >= max_paginas:
            break

    return encontradas


def resolver_paginas_solicitadas(
    db: Session, apostila_id: int, numeros_pagina: list[int]
) -> dict[int, PaginaResolvida]:
    """Para cada página pedida (número impresso, o que o usuário digitou),
    resolve a linha real no banco — texto E imagem — buscando numa pequena
    janela ao redor do número pedido, porque numero_pagina é a posição
    bruta no PDF, que pode estar deslocada da numeração impressa (ver
    _extrair_pagina_do_rodape). Prioriza a linha cujo rodapé do texto bate
    exatamente com o número pedido; se a página não tiver texto (rodapé
    ilegível, ex.: página só de imagem), cai no numero_pagina bruto exato.

    Retorna apenas as páginas em que achou algo (texto e/ou imagem) — página
    sem nenhum dos dois simplesmente não aparece no resultado.
    """
    if not numeros_pagina:
        return {}

    minimo = max(1, min(numeros_pagina) - _JANELA_BUSCA_OFFSET)
    maximo = max(numeros_pagina) + _JANELA_BUSCA_OFFSET

    rows = (
        db.query(
            ApostilaIAPagina.numero_pagina,
            ApostilaIAPagina.texto_extraido,
            ApostilaIAPagina.imagem_path,
        )
        .filter(
            ApostilaIAPagina.apostila_id == apostila_id,
            ApostilaIAPagina.numero_pagina.between(minimo, maximo),
        )
        .all()
    )

    por_rodape: dict[int, PaginaResolvida] = {}
    por_numero_pagina: dict[int, PaginaResolvida] = {}
    for numero_pagina, texto, imagem_path in rows:
        texto_limpo = (texto or "").strip() or None
        resolvida = PaginaResolvida(int(numero_pagina), texto_limpo, imagem_path)
        por_numero_pagina[int(numero_pagina)] = resolvida
        if texto_limpo:
            pagina_rodape = _extrair_pagina_do_rodape(texto_limpo)
            if pagina_rodape is not None and pagina_rodape not in por_rodape:
                por_rodape[pagina_rodape] = resolvida

    resultado: dict[int, PaginaResolvida] = {}
    for pagina in numeros_pagina:
        if pagina in por_rodape:
            resultado[pagina] = por_rodape[pagina]
        elif pagina in por_numero_pagina:
            candidata = por_numero_pagina[pagina]
            if candidata.tem_texto or candidata.tem_imagem:
                resultado[pagina] = candidata
    return resultado


def montar_citacoes_paginas(paginas: dict[int, PaginaResolvida]) -> list[dict]:
    return [
        {
            "filename": f"pagina_{numero:04d}.txt",
            "texto": (pagina.texto or "")[:500],
        }
        for numero, pagina in sorted(paginas.items())
    ]
