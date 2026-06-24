"""Chunking de texto extraído de apostilas.

Estimativa de tokens: usamos uma heurística simples baseada em contagem de
palavras (len(texto.split()) * 1.3), em vez de `tiktoken`, para manter a
dependência da imagem Docker mínima neste MVP. A heurística é suficiente
para decidir limites de ~800-1200 tokens por chunk sem precisão de tokenizer
exata. Se for necessário precisão exata futuramente, trocar por `tiktoken`.
"""
from __future__ import annotations

from dataclasses import dataclass, field

from app.services.pdf_service import PageText

MIN_TOKENS_PER_CHUNK = 800
MAX_TOKENS_PER_CHUNK = 1200


def estimar_tokens(texto: str) -> float:
    return len(texto.split()) * 1.3


@dataclass
class Chunk:
    apostila_id: int
    pagina_inicio: int
    pagina_fim: int
    conteudo: str
    metadata_json: dict = field(default_factory=dict)


def montar_chunks(apostila_id: int, paginas: list[PageText]) -> list[Chunk]:
    """Agrupa páginas consecutivas (nunca distantes entre si) até atingir
    entre MIN_TOKENS_PER_CHUNK e MAX_TOKENS_PER_CHUNK tokens estimados.
    """
    chunks: list[Chunk] = []

    buffer_textos: list[str] = []
    buffer_paginas: list[int] = []
    buffer_tokens = 0.0

    def flush() -> None:
        nonlocal buffer_textos, buffer_paginas, buffer_tokens
        if not buffer_textos:
            return
        conteudo = "\n\n".join(buffer_textos).strip()
        if conteudo:
            chunks.append(
                Chunk(
                    apostila_id=apostila_id,
                    pagina_inicio=buffer_paginas[0],
                    pagina_fim=buffer_paginas[-1],
                    conteudo=conteudo,
                    metadata_json={
                        "apostila_id": apostila_id,
                        "paginas": list(buffer_paginas),
                        "tipo": "conteudo_apostila",
                    },
                )
            )
        buffer_textos = []
        buffer_paginas = []
        buffer_tokens = 0.0

    for pagina in paginas:
        if not pagina.texto:
            # Páginas vazias (possivelmente escaneadas) não geram chunk,
            # mas fecham o buffer atual para não misturar páginas distantes
            # com um "buraco" de conteúdo no meio.
            flush()
            continue

        tokens_pagina = estimar_tokens(pagina.texto)

        if buffer_tokens + tokens_pagina > MAX_TOKENS_PER_CHUNK and buffer_textos:
            flush()

        buffer_textos.append(pagina.texto)
        buffer_paginas.append(pagina.numero_pagina)
        buffer_tokens += tokens_pagina

        if buffer_tokens >= MIN_TOKENS_PER_CHUNK:
            flush()

    flush()
    return chunks
