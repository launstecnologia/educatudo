-- Rollback de 2026_10_28_regra_escala.sql

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'regras_academicas'
    AND COLUMN_NAME = 'conceito_minimo'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `regras_academicas` DROP COLUMN `conceito_minimo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'regras_academicas'
    AND COLUMN_NAME = 'escala'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `regras_academicas` DROP COLUMN `escala`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
