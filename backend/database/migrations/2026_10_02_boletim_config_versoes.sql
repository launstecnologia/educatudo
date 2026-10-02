-- Cada salvamento da configuração de um evento de notas vira uma versão.
-- A versão anterior permanece para recuperação. Tenant. Idempotente.
-- Rollback: 2026_10_02_boletim_config_versoes_rollback.sql

CREATE TABLE IF NOT EXISTS `boletim_config_versoes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `regra_id` INT NOT NULL,
  `versao` INT NOT NULL,
  `snapshot_json` MEDIUMTEXT NOT NULL,
  `usuario_id` INT NULL,
  `usuario_nome` VARCHAR(150) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_boletim_config_versoes` (`regra_id`, `versao`),
  KEY `idx_boletim_config_versoes_regra` (`regra_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
