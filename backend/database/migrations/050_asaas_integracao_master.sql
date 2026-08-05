-- MASTER apenas (nome contém "master"). Banco administrativo.

CREATE TABLE IF NOT EXISTS `asaas_master_config` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `api_key_encrypted` TEXT NULL COMMENT 'API key Asaas (criptografada)',
  `environment` ENUM('sandbox','production') NOT NULL DEFAULT 'sandbox',
  `webhook_token` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Token enviado no header asaas-access-token',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `asaas_master_config` (`id`, `api_key_encrypted`, `environment`, `webhook_token`) VALUES (1, NULL, 'sandbox', '');

CREATE TABLE IF NOT EXISTS `asaas_webhook_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `payment_id` VARCHAR(64) NULL,
  `event_type` VARCHAR(80) NULL,
  `validation_ok` TINYINT(1) NOT NULL DEFAULT 0,
  `http_status` SMALLINT NULL,
  `payload_hash` CHAR(64) NULL,
  `note` VARCHAR(500) NULL,
  PRIMARY KEY (`id`),
  KEY `idx_asaas_webhook_created` (`created_at`),
  KEY `idx_asaas_webhook_payment` (`payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asaas_payment_processed` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_id` VARCHAR(64) NOT NULL,
  `event_kind` ENUM('credit_applied','refund_debit') NOT NULL,
  `processed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `external_reference` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_asaas_pay_kind` (`payment_id`, `event_kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
