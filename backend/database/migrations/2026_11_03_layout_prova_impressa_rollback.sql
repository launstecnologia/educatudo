-- Rollback de 2026_11_03_layout_prova_impressa.sql
-- Remove o JSON do layout da prova impressa. A impressão volta ao padrão.

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'provas_blocos'
    AND COLUMN_NAME = 'layout_impressao'
);
SET @sql := IF(
  @col_exists > 0,
  'ALTER TABLE `provas_blocos` DROP COLUMN `layout_impressao`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
