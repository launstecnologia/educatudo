-- Rollback de 2026_11_10_escopo_arredondamento.sql

SET @db := DATABASE();

SET @tem := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND COLUMN_NAME = 'escopo_arredondamento'
);
SET @sql := IF(@tem > 0,
  'ALTER TABLE `regras_academicas` DROP COLUMN `escopo_arredondamento`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
