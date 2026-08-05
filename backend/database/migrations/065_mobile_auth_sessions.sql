-- Sessões revogáveis da API mobile. Refresh tokens são armazenados somente
-- como hash; o token original existe apenas no dispositivo do responsável.
CREATE TABLE IF NOT EXISTS mobile_auth_sessions (
    id CHAR(36) NOT NULL,
    parent_id INT NOT NULL,
    refresh_token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mobile_auth_refresh (refresh_token_hash),
    KEY idx_mobile_auth_parent_active (parent_id, revoked_at, expires_at),
    CONSTRAINT fk_mobile_auth_parent
        FOREIGN KEY (parent_id) REFERENCES responsaveis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
