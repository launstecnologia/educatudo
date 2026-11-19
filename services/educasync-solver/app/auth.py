"""Dependência de autenticação interna (header X-Internal-Api-Key)."""
from __future__ import annotations

import hmac

from fastapi import Header, HTTPException

from app.config import get_settings


async def require_internal_api_key(
    x_internal_api_key: str | None = Header(default=None, alias="X-Internal-Api-Key"),
) -> None:
    esperada = get_settings().internal_api_key
    if (
        not esperada
        or not x_internal_api_key
        or not hmac.compare_digest(x_internal_api_key.encode(), esperada.encode())
    ):
        raise HTTPException(status_code=401, detail={"error": "unauthorized"})
