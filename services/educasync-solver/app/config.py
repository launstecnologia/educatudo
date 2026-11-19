"""Configurações da aplicação, carregadas via variáveis de ambiente (.env)."""
from __future__ import annotations

import os
from functools import lru_cache

from dotenv import load_dotenv

load_dotenv()


class Settings:
    app_name: str = os.getenv("APP_NAME", "educasync-solver")
    app_env: str = os.getenv("APP_ENV", "local")
    port: int = int(os.getenv("PORT", "8091"))

    # Sem default: chave vazia => todas as rotas protegidas respondem 401.
    internal_api_key: str = os.getenv("INTERNAL_API_KEY", "")

    max_time_s_limite: float = float(os.getenv("MAX_TIME_S_LIMITE", "300"))
    max_workers_limite: int = int(os.getenv("MAX_WORKERS_LIMITE", "8"))


@lru_cache
def get_settings() -> Settings:
    return Settings()
