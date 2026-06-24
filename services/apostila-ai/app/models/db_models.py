"""Modelos SQLAlchemy ORM para as tabelas `apostilas_ia` / `apostila_ia_*`.

Estas tabelas são compartilhadas com a plataforma PHP (EducaTudo). O schema
SQL real (CREATE TABLE) é versionado nas migrations do repositório PHP — este
arquivo apenas espelha esse schema para que o serviço Python leia/escreva
de forma consistente com o lado PHP.
"""
from __future__ import annotations

import enum

from sqlalchemy import (
    BigInteger,
    DateTime,
    Enum,
    ForeignKey,
    Integer,
    JSON,
    String,
    Text,
    func,
)
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.database import Base


class ApostilaStatus(str, enum.Enum):
    pendente = "pendente"
    processando = "processando"
    pronto = "pronto"
    erro = "erro"


class ExercicioTipo(str, enum.Enum):
    objetiva = "objetiva"
    discursiva = "discursiva"
    verdadeiro_falso = "verdadeiro_falso"
    associacao = "associacao"
    outro = "outro"


class ExercicioDificuldade(str, enum.Enum):
    facil = "facil"
    media = "media"
    dificil = "dificil"
    nao_identificada = "nao_identificada"


class ApostilaIA(Base):
    __tablename__ = "apostilas_ia"

    id: Mapped[int] = mapped_column(BigInteger, primary_key=True, autoincrement=True)
    titulo: Mapped[str] = mapped_column(String(255))
    escola_id: Mapped[int | None] = mapped_column(BigInteger, nullable=True)
    serie_id: Mapped[int | None] = mapped_column(BigInteger, nullable=True)
    turma_id: Mapped[int | None] = mapped_column(BigInteger, nullable=True)
    disciplina_id: Mapped[int | None] = mapped_column(BigInteger, nullable=True)
    professor_id: Mapped[int | None] = mapped_column(BigInteger, nullable=True)
    arquivo_pdf: Mapped[str] = mapped_column(String(500))
    status: Mapped[ApostilaStatus] = mapped_column(
        Enum(ApostilaStatus, native_enum=True), default=ApostilaStatus.pendente
    )
    total_paginas: Mapped[int] = mapped_column(Integer, default=0)
    erro: Mapped[str | None] = mapped_column(Text, nullable=True)
    vector_store_id: Mapped[str | None] = mapped_column(String(255), nullable=True)
    created_at: Mapped[object] = mapped_column(DateTime, server_default=func.now())
    updated_at: Mapped[object] = mapped_column(
        DateTime, server_default=func.now(), onupdate=func.now()
    )

    paginas: Mapped[list["ApostilaIAPagina"]] = relationship(
        back_populates="apostila", cascade="all, delete-orphan"
    )
    chunks: Mapped[list["ApostilaIAChunk"]] = relationship(
        back_populates="apostila", cascade="all, delete-orphan"
    )
    exercicios: Mapped[list["ApostilaIAExercicio"]] = relationship(
        back_populates="apostila", cascade="all, delete-orphan"
    )
    conversas: Mapped[list["ApostilaIAConversa"]] = relationship(
        back_populates="apostila", cascade="all, delete-orphan"
    )


class ApostilaIAPagina(Base):
    __tablename__ = "apostila_ia_paginas"

    id: Mapped[int] = mapped_column(BigInteger, primary_key=True, autoincrement=True)
    apostila_id: Mapped[int] = mapped_column(
        BigInteger, ForeignKey("apostilas_ia.id", ondelete="CASCADE")
    )
    numero_pagina: Mapped[int] = mapped_column(Integer)
    texto_extraido: Mapped[str | None] = mapped_column(Text().with_variant(Text, "mysql"), nullable=True)
    imagem_path: Mapped[str | None] = mapped_column(String(500), nullable=True)
    created_at: Mapped[object] = mapped_column(DateTime, server_default=func.now())

    apostila: Mapped["ApostilaIA"] = relationship(back_populates="paginas")


class ApostilaIAChunk(Base):
    __tablename__ = "apostila_ia_chunks"

    id: Mapped[int] = mapped_column(BigInteger, primary_key=True, autoincrement=True)
    apostila_id: Mapped[int] = mapped_column(
        BigInteger, ForeignKey("apostilas_ia.id", ondelete="CASCADE")
    )
    pagina_inicio: Mapped[int] = mapped_column(Integer)
    pagina_fim: Mapped[int] = mapped_column(Integer)
    conteudo: Mapped[str] = mapped_column(Text)
    metadata_json: Mapped[dict | None] = mapped_column(JSON, nullable=True)
    embedding_provider: Mapped[str] = mapped_column(String(50), default="openai")
    created_at: Mapped[object] = mapped_column(DateTime, server_default=func.now())

    apostila: Mapped["ApostilaIA"] = relationship(back_populates="chunks")


class ApostilaIAExercicio(Base):
    __tablename__ = "apostila_ia_exercicios"

    id: Mapped[int] = mapped_column(BigInteger, primary_key=True, autoincrement=True)
    apostila_id: Mapped[int] = mapped_column(
        BigInteger, ForeignKey("apostilas_ia.id", ondelete="CASCADE")
    )
    pagina: Mapped[int] = mapped_column(Integer)
    capitulo: Mapped[str | None] = mapped_column(String(255), nullable=True)
    tema: Mapped[str | None] = mapped_column(String(255), nullable=True)
    tipo: Mapped[ExercicioTipo] = mapped_column(
        Enum(ExercicioTipo, native_enum=True), default=ExercicioTipo.outro
    )
    enunciado: Mapped[str] = mapped_column(Text)
    alternativas: Mapped[dict | list | None] = mapped_column(JSON, nullable=True)
    gabarito: Mapped[str | None] = mapped_column(Text, nullable=True)
    dificuldade: Mapped[ExercicioDificuldade] = mapped_column(
        Enum(ExercicioDificuldade, native_enum=True),
        default=ExercicioDificuldade.nao_identificada,
    )
    created_at: Mapped[object] = mapped_column(DateTime, server_default=func.now())

    apostila: Mapped["ApostilaIA"] = relationship(back_populates="exercicios")


class ApostilaIAConversa(Base):
    __tablename__ = "apostila_ia_conversas"

    id: Mapped[int] = mapped_column(BigInteger, primary_key=True, autoincrement=True)
    apostila_id: Mapped[int] = mapped_column(
        BigInteger, ForeignKey("apostilas_ia.id", ondelete="CASCADE")
    )
    professor_id: Mapped[int | None] = mapped_column(BigInteger, nullable=True)
    pergunta: Mapped[str] = mapped_column(Text)
    resposta: Mapped[str] = mapped_column(Text)
    paginas_usadas: Mapped[list | None] = mapped_column(JSON, nullable=True)
    created_at: Mapped[object] = mapped_column(DateTime, server_default=func.now())

    apostila: Mapped["ApostilaIA"] = relationship(back_populates="conversas")
