-- Início e fim de cada período do ano letivo (bimestre, trimestre, semestre ou etapa).
-- Tenant. Sem escola_id: o isolamento é o banco. Idempotente.
-- Rollback: 2026_10_28_ano_letivo_periodos_rollback.sql

CREATE TABLE IF NOT EXISTS `ano_letivo_periodos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `ano_letivo_id` INT(11) NOT NULL,
  `numero` TINYINT UNSIGNED NOT NULL COMMENT '1 = primeiro período da divisão do ano',
  `data_inicio` DATE NOT NULL,
  `data_fim` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ano_letivo_periodo_numero` (`ano_letivo_id`, `numero`),
  CONSTRAINT `fk_ano_letivo_periodos_ano`
    FOREIGN KEY (`ano_letivo_id`) REFERENCES `ano_letivo` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
