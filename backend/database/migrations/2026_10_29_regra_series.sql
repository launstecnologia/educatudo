-- Várias séries ou anos escolares na mesma regra de aprovação.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_29_regra_series_rollback.sql

CREATE TABLE IF NOT EXISTS `regras_academicas_series` (
  `regra_id` INT NOT NULL,
  `serie_id` INT NOT NULL,
  PRIMARY KEY (`regra_id`, `serie_id`),
  KEY `idx_regras_academicas_series_serie` (`serie_id`),
  CONSTRAINT `fk_regras_acad_series_regra`
    FOREIGN KEY (`regra_id`) REFERENCES `regras_academicas` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_regras_acad_series_serie`
    FOREIGN KEY (`serie_id`) REFERENCES `serie` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `regras_academicas_series` (`regra_id`, `serie_id`)
SELECT `id`, `serie_id`
FROM `regras_academicas`
WHERE `serie_id` IS NOT NULL AND `serie_id` > 0;
