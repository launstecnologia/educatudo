-- MASTER apenas. Snapshot diário de KPIs do dashboard Master.

CREATE TABLE IF NOT EXISTS `master_dashboard_kpis` (
  `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `total_logins_sucesso` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `total_jornadas` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `total_provas` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `modulos_json` JSON NOT NULL,
  `gerado_em` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Snapshot global de KPIs do painel Master (agregado pelo CRON)';

CREATE TABLE IF NOT EXISTS `master_dashboard_kpis_escolas` (
  `escola_id` INT UNSIGNED NOT NULL,
  `total_logins_sucesso` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `total_jornadas` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `total_provas` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `modulos_json` JSON NOT NULL,
  `gerado_em` DATETIME NOT NULL,
  PRIMARY KEY (`escola_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Breakdown por escola dos KPIs do dashboard Master';
