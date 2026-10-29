-- Rollback de 2026_10_28_evento_rascunho.sql

SET @db := DATABASE();
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'eventos_avaliativos'
    AND COLUMN_NAME = 'rascunho'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `eventos_avaliativos` DROP COLUMN `rascunho`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
