"""Extração de texto página-a-página de PDFs usando PyMuPDF (fitz)."""
from __future__ import annotations

import io
import logging
import os

import fitz  # PyMuPDF
from PIL import Image

from app.config import get_settings

logger = logging.getLogger("apostila_ai.pdf_service")

PDF_MAGIC_BYTES = b"%PDF-"


class PdfValidationError(Exception):
    """Erro de validação de arquivo/caminho de PDF."""


class PageText:
    def __init__(
        self,
        numero_pagina: int,
        texto: str,
        possivel_escaneada: bool,
        imagem_path: str | None = None,
    ):
        self.numero_pagina = numero_pagina
        self.texto = texto
        self.possivel_escaneada = possivel_escaneada
        self.imagem_path = imagem_path


AREA_MINIMA_IMAGEM = 4000  # px² — filtra ícones/decorações pequenas, mantém figuras reais


def _extrair_maior_imagem_da_pagina(
    doc: "fitz.Document", page: "fitz.Page", apostila_id: int, numero_pagina: int, pages_dir: str
) -> str | None:
    """Extrai a maior imagem embutida na página (geralmente a figura/diagrama
    associado a um exercício ou explicação) e salva em disco. Páginas com
    várias imagens pequenas (ícones, bordas decorativas) só geram arquivo se
    pelo menos uma imagem for grande o suficiente para ser conteúdo real.
    Retorna o caminho absoluto salvo, ou None se a página não tiver imagem
    relevante.
    """
    try:
        image_list = page.get_images(full=True)
    except Exception:
        return None

    if not image_list:
        return None

    melhor_info = None
    melhor_area = 0
    for img in image_list:
        xref = img[0]
        try:
            info = doc.extract_image(xref)
        except Exception:
            continue
        area = info.get("width", 0) * info.get("height", 0)
        if area > melhor_area:
            melhor_area = area
            melhor_info = info

    if melhor_info is None or melhor_area < AREA_MINIMA_IMAGEM:
        return None

    dest_dir = os.path.join(pages_dir, str(apostila_id))
    os.makedirs(dest_dir, exist_ok=True)
    dest_path = os.path.join(dest_dir, f"pagina_{numero_pagina:04d}.png")

    # Sempre converte para PNG via Pillow, independente do formato original
    # extraído (PDFs de apostilas impressas frequentemente embutem imagens em
    # JPEG2000/.jpx, CMYK ou outros formatos que navegadores não conseguem
    # exibir em <img>; PNG garante compatibilidade universal).
    try:
        imagem = Image.open(io.BytesIO(melhor_info["image"]))
        if imagem.mode not in ("RGB", "RGBA", "L"):
            imagem = imagem.convert("RGB")
        imagem.save(dest_path, format="PNG")
    except Exception:
        logger.exception(
            "erro_converter_imagem_pagina apostila_id=%s pagina=%s ext_original=%s",
            apostila_id,
            numero_pagina,
            melhor_info.get("ext"),
        )
        return None

    return dest_path


def gerar_capa_apostila(pdf_path: str, apostila_id: int, pages_dir: str) -> str | None:
    """Renderiza a página 1 inteira do PDF como imagem (não a maior figura
    embutida — a página inteira, igual a capa de um livro) e salva como
    capa.png. Diferente de _extrair_maior_imagem_da_pagina, isso funciona
    mesmo que a capa não tenha nenhuma imagem rasterizada própria (texto
    vetorial, por exemplo) — sempre gera algo para mostrar na galeria de
    "Meu Material".
    """
    dest_dir = os.path.join(pages_dir, str(apostila_id))
    os.makedirs(dest_dir, exist_ok=True)
    dest_path = os.path.join(dest_dir, "capa.png")

    try:
        doc = fitz.open(pdf_path)
        try:
            if len(doc) == 0:
                return None
            pagina = doc.load_page(0)
            pixmap = pagina.get_pixmap(matrix=fitz.Matrix(2, 2))
            pixmap.save(dest_path)
        finally:
            doc.close()
    except Exception:
        logger.exception("erro_gerar_capa_apostila apostila_id=%s", apostila_id)
        return None

    return dest_path


def validar_pdf_path(pdf_path: str, base_dir: str | None = None) -> str:
    """Valida que o caminho é seguro (sem path traversal) e que o arquivo é
    de fato um PDF (checagem de magic bytes), além do limite de tamanho.

    Retorna o caminho absoluto normalizado se válido, ou levanta
    PdfValidationError.
    """
    settings = get_settings()

    if ".." in pdf_path:
        raise PdfValidationError("Caminho inválido: não pode conter '..'")

    abs_path = os.path.abspath(pdf_path)

    if base_dir:
        abs_base = os.path.abspath(base_dir)
        if not abs_path.startswith(abs_base + os.sep) and abs_path != abs_base:
            raise PdfValidationError("Caminho fora do diretório permitido")

    if not os.path.isfile(abs_path):
        raise PdfValidationError("Arquivo não encontrado")

    size_bytes = os.path.getsize(abs_path)
    if size_bytes > settings.max_upload_bytes:
        raise PdfValidationError(
            f"Arquivo excede o limite de {settings.max_upload_mb}MB"
        )

    if size_bytes == 0:
        raise PdfValidationError("Arquivo vazio")

    with open(abs_path, "rb") as f:
        header = f.read(5)
    if header != PDF_MAGIC_BYTES:
        raise PdfValidationError("Arquivo não é um PDF válido (magic bytes incorretos)")

    return abs_path


def extrair_texto_por_pagina(
    pdf_path: str, apostila_id: int | None = None, pages_dir: str | None = None
) -> list[PageText]:
    """Abre o PDF e extrai o texto de cada página, e a maior imagem embutida
    (quando houver e `apostila_id`/`pages_dir` forem informados) — útil para
    exercícios cujo enunciado depende de uma figura/diagrama.

    Páginas sem texto extraível (provável digitalização/scan) são marcadas
    com possivel_escaneada=True e texto vazio — não levanta exceção, apenas
    loga e segue (OCR é trabalho futuro, fora de escopo nesta versão).
    """
    paginas: list[PageText] = []
    doc = fitz.open(pdf_path)
    try:
        for index in range(len(doc)):
            page = doc.load_page(index)
            numero_pagina = index + 1
            texto = page.get_text("text") or ""
            texto = texto.strip()
            possivel_escaneada = len(texto) == 0
            if possivel_escaneada:
                logger.info(
                    "pagina_sem_texto_extraivel possivel_pagina_escaneada=%s pagina=%s",
                    True,
                    numero_pagina,
                )

            imagem_path = None
            if apostila_id is not None and pages_dir is not None:
                imagem_path = _extrair_maior_imagem_da_pagina(
                    doc, page, apostila_id, numero_pagina, pages_dir
                )

            paginas.append(PageText(numero_pagina, texto, possivel_escaneada, imagem_path))
    finally:
        doc.close()
    return paginas
