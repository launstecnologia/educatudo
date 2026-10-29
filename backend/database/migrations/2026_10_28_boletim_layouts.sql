-- Layout do boletim oficial no modelo de eventos avaliativos.
-- Uma linha por ano letivo. O demonstrativo de notas continua no cadastro atual.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_28_boletim_layouts_rollback.sql

CREATE TABLE IF NOT EXISTS `boletim_layouts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ano_letivo` SMALLINT UNSIGNED NOT NULL,
  `layout_json` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_boletim_layouts_ano` (`ano_letivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
