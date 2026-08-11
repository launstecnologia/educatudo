-- Registro aditivo de instalações Android para push via Firebase Cloud Messaging.
-- A tabela armazena tokens; credenciais privadas do Firebase permanecem apenas no servidor.
CREATE TABLE IF NOT EXISTS mobile_devices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id INT NOT NULL,
    device_id VARCHAR(128) NOT NULL,
    fcm_token VARCHAR(4096) NULL,
    token_hash CHAR(64) NULL,
    platform VARCHAR(20) NOT NULL DEFAULT 'android',
    app_version VARCHAR(50) NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mobile_devices_device (device_id),
    UNIQUE KEY uq_mobile_devices_token_hash (token_hash),
    KEY idx_mobile_devices_parent_enabled (parent_id, enabled),
    KEY idx_mobile_devices_last_seen (last_seen_at),
    CONSTRAINT fk_mobile_devices_parent
        FOREIGN KEY (parent_id) REFERENCES responsaveis (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
