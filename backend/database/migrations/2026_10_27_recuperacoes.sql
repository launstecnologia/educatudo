-- Formas de recuperação do ano (a cada bimestre, a cada 2, ou só a final).
-- Tenant. Sem escola_id: o isolamento é o banco. Idempotente.
-- Rollback: 2026_10_27_recuperacoes_rollback.sql

CREATE TABLE IF NOT EXISTS `recuperacoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ano_letivo` INT NOT NULL,
  `nome` VARCHAR(120) NOT NULL,
  `quando` VARCHAR(20) NOT NULL,
  `nota_minima` DECIMAL(8,2) NULL DEFAULT NULL,
  `nota_maxima` DECIMAL(8,2) NULL DEFAULT NULL,
  `efeito` VARCHAR(30) NOT NULL,
  `ordem` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_recuperacoes_ano` (`ano_letivo`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
