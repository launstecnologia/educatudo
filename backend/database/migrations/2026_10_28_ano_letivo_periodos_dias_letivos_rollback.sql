-- Rollback de 2026_10_28_ano_letivo_periodos_dias_letivos.sql
-- Remove só a quantidade de dias letivos. As datas do período permanecem.

SET @db := DATABASE();
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'ano_letivo_periodos'
    AND COLUMN_NAME = 'dias_letivos'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `ano_letivo_periodos` DROP COLUMN `dias_letivos`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
