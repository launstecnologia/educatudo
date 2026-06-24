"""Dependência de autenticação interna (header X-Internal-Api-Key)."""
from __future__ import annotations

from fastapi import Header, HTTPException

from app.config import get_settings


async def require_internal_api_key(
    x_internal_api_key: str | None = Header(default=None, alias="X-Internal-Api-Key"),
) -> None:
    settings = get_settings()
    if not x_internal_api_key or x_internal_api_key != settings.internal_api_key:
        raise HTTPException(status_code=401, detail={"error": "unauthorized"})
