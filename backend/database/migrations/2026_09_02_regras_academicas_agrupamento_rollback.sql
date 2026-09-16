-- Rollback de 2026_09_02_regras_academicas_agrupamento.sql (remove só o que essa migration adicionou).

SET @db := DATABASE();

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND INDEX_NAME = 'idx_regras_academicas_agrupamento'
);
SET @sql := IF(
  @idx > 0,
  'ALTER TABLE `regras_academicas` DROP KEY `idx_regras_academicas_agrupamento`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND COLUMN_NAME = 'agrupamento_id'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `regras_academicas` DROP COLUMN `agrupamento_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
