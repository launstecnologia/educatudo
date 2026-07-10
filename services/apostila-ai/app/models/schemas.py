"""Modelos Pydantic (request/response) da API."""
from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, Field


class ProcessarApostilaRequest(BaseModel):
    pdf_path: str = Field(..., description="Caminho absoluto do PDF no servidor")
    titulo: str = Field(..., max_length=255)


class ProcessarApostilaResponse(BaseModel):
    apostila_id: int
    status: str
    total_paginas: int = 0
    paginas_sem_texto: int = 0
    chunks_criados: int = 0
    exercicios_detectados: int = 0
    vector_store_id: str | None = None
    erro: str | None = None
    tempo_processamento_segundos: float | None = None


class ChatApostilaRequest(BaseModel):
    apostila_id: int
    professor_id: int | None = None
    sessao_id: int | None = None
    pergunta: str = Field(..., min_length=1)


class ChatApostilaResponse(BaseModel):
    resposta: str
    conversa_id: int | None = None
    sessao_id: int | None = None
    paginas_usadas: list[int] = Field(default_factory=list)
    paginas_com_imagem: list[int] = Field(default_factory=list)
    fontes: list["FonteResposta"] = Field(default_factory=list)


class FonteResposta(BaseModel):
    pagina: int
    trecho: str


class ExercicioResponse(BaseModel):
    id: int
    apostila_id: int
    pagina: int
    capitulo: str | None
    tema: str | None
    tipo: str
    enunciado: str
    alternativas: list | dict | None
    gabarito: str | None
    dificuldade: str
    tem_imagem: bool = False

    class Config:
        from_attributes = True


class GerarProvaRequest(BaseModel):
    professor_id: int | None = None
    tema: str | None = None
    quantidade: int = Field(default=10, ge=1, le=100)
    nivel: Literal["facil", "media", "dificil"] | None = None
    incluir_gabarito: bool = False


class QuestaoProva(BaseModel):
    id: int
    pagina: int
    tema: str | None
    tipo: str
    enunciado: str
    alternativas: list | dict | None
    gabarito: str | None = None
    dificuldade: str
    tem_imagem: bool = False


class GerarProvaResponse(BaseModel):
    apostila_id: int
    quantidade_solicitada: int
    quantidade_retornada: int
    questoes: list[QuestaoProva]
    nota: str | None = None


class ContextoApostilaRequest(BaseModel):
    capitulo_ou_tema: str = Field(..., min_length=1, max_length=255)
    num_slides: int = Field(default=8, ge=3, le=30)


class ContextoApostilaResponse(BaseModel):
    conteudo_sugerido: str
    paginas_usadas: list[int] = Field(default_factory=list)


class ErrorResponse(BaseModel):
    error: str
