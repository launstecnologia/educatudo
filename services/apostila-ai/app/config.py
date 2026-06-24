"""Configurações da aplicação, carregadas via variáveis de ambiente (.env)."""
from __future__ import annotations

import os
from functools import lru_cache

from dotenv import load_dotenv

load_dotenv()


class Settings:
    app_name: str = os.getenv("APP_NAME", "apostila-ai")
    app_env: str = os.getenv("APP_ENV", "local")
    app_port: int = int(os.getenv("APP_PORT", "8088"))

    openai_api_key: str = os.getenv("OPENAI_API_KEY", "")
    openai_model: str = os.getenv("OPENAI_MODEL", "gpt-4.1-mini")
    openai_embedding_model: str = os.getenv(
        "OPENAI_EMBEDDING_MODEL", "text-embedding-3-small"
    )

    database_url: str = os.getenv(
        "DATABASE_URL", "mysql+pymysql://user:password@mysql:3306/educatudo"
    )

    storage_path: str = os.getenv("STORAGE_PATH", "/app/app/storage")
    max_upload_mb: int = int(os.getenv("MAX_UPLOAD_MB", "200"))

    internal_api_key: str = os.getenv("INTERNAL_API_KEY", "trocar_essa_chave")

    @property
    def uploads_path(self) -> str:
        return os.path.join(self.storage_path, "uploads")

    @property
    def pages_path(self) -> str:
        return os.path.join(self.storage_path, "pages")

    @property
    def max_upload_bytes(self) -> int:
        return self.max_upload_mb * 1024 * 1024


@lru_cache
def get_settings() -> Settings:
    return Settings()
