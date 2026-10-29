-- Rollback de 2026_10_29_regra_series.sql
-- A coluna regras_academicas.serie_id permanece.

SET @db := DATABASE();

SET @fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'regras_academicas_series'
    AND CONSTRAINT_NAME = 'fk_regras_acad_series_serie'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
  @fk > 0,
  'ALTER TABLE `regras_academicas_series` DROP FOREIGN KEY `fk_regras_acad_series_serie`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'regras_academicas_series'
    AND CONSTRAINT_NAME = 'fk_regras_acad_series_regra'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
  @fk > 0,
  'ALTER TABLE `regras_academicas_series` DROP FOREIGN KEY `fk_regras_acad_series_regra`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `regras_academicas_series`;
