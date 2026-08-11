-- Pareamento seguro de tablets/leitores faciais por tenant.

CREATE TABLE IF NOT EXISTS `facial_device_pairing_codes` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `code_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_by_user_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_facial_pairing_code_hash` (`code_hash`),
  KEY `idx_facial_pairing_expiration` (`expires_at`, `used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `facial_devices` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `device_uid` varchar(128) NOT NULL,
  `name` varchar(120) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `paired_by_user_id` int DEFAULT NULL,
  `paired_at` datetime NOT NULL,
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_facial_device_uid` (`device_uid`),
  UNIQUE KEY `uq_facial_device_token` (`token_hash`),
  KEY `idx_facial_device_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
