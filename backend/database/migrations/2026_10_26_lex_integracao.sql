-- Vínculos e histórico da sincronização LEX no banco da escola.
-- Tenant. Sem escola_id: o isolamento é o banco. Idempotente.
-- Rollback: 2026_10_26_lex_integracao_rollback.sql

CREATE TABLE IF NOT EXISTS `lex_vinculos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo_entidade` VARCHAR(40) NOT NULL COMMENT 'aluno, professor, responsavel, usuario, aluno_responsavel',
  `id_local` INT NOT NULL,
  `id_lex` VARCHAR(80) NOT NULL,
  `account_id` VARCHAR(64) NULL,
  `origem` VARCHAR(20) NOT NULL DEFAULT 'lex',
  `status` VARCHAR(20) NOT NULL DEFAULT 'ativo',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_lex_vinculo_tipo_lex` (`tipo_entidade`, `id_lex`),
  UNIQUE KEY `uk_lex_vinculo_tipo_local` (`tipo_entidade`, `id_local`),
  KEY `idx_lex_vinculo_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lex_sincronizacoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sync_id` VARCHAR(64) NULL,
  `sync_date` VARCHAR(40) NULL,
  `modo` ENUM('simulacao','real') NOT NULL,
  `status` ENUM('sucesso','falha') NOT NULL,
  `contagens_json` TEXT NULL,
  `mensagem` VARCHAR(500) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lex_sincronizacoes_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lex_sincronizacao_eventos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sincronizacao_id` BIGINT UNSIGNED NOT NULL,
  `nivel` ENUM('info','conflito','ignorado','erro') NOT NULL,
  `tipo_entidade` VARCHAR(40) NULL,
  `id_lex` VARCHAR(80) NULL,
  `mensagem` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_lex_eventos_sync` (`sincronizacao_id`),
  CONSTRAINT `fk_lex_eventos_sincronizacao`
    FOREIGN KEY (`sincronizacao_id`) REFERENCES `lex_sincronizacoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
