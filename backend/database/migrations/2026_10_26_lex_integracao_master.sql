-- Configuração da integração LEX (Conexia) por escola.
-- Master. O segredo fica cifrado com MasterSecretVault. Idempotente.
-- Rollback: 2026_10_26_lex_integracao_master_rollback.sql

CREATE TABLE IF NOT EXISTS `lex_escolas_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `escola_id` INT UNSIGNED NOT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 0,
  `ambiente` ENUM('homologacao','producao') NOT NULL DEFAULT 'homologacao',
  `client_id` VARCHAR(255) NOT NULL DEFAULT '',
  `client_secret_cifrado` TEXT NULL,
  `tenant_id` VARCHAR(64) NOT NULL DEFAULT '',
  `escola_lex_id` VARCHAR(64) NOT NULL DEFAULT '',
  `external_id` VARCHAR(128) NOT NULL DEFAULT '',
  `direcao` ENUM('lex_para_educatudo') NOT NULL DEFAULT 'lex_para_educatudo',
  `ultima_sincronizacao` DATETIME NULL,
  `ultimo_resultado` VARCHAR(40) NULL,
  `ultimo_sync_id` VARCHAR(64) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lex_escolas_config_escola` (`escola_id`),
  CONSTRAINT `fk_lex_escolas_config_escola`
    FOREIGN KEY (`escola_id`) REFERENCES `escolas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Credenciais e vínculo LEX de cada escola EducaTudo';
