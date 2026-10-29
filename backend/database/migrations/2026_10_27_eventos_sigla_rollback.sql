-- Rollback de 2026_10_27_eventos_sigla.sql

SET @db := DATABASE();
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'eventos_avaliativos'
    AND COLUMN_NAME = 'sigla'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `eventos_avaliativos` DROP COLUMN `sigla`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
