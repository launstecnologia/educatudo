-- Rollback de 2026_09_12_ano_letivo_periodo_tipo.sql
-- Remove só a coluna periodo_tipo de ano_letivo.

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'ano_letivo' AND COLUMN_NAME = 'periodo_tipo'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `ano_letivo` DROP COLUMN `periodo_tipo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
