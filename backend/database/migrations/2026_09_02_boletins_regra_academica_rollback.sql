-- Rollback de 2026_09_02_boletins_regra_academica.sql
-- Remove só o vínculo boletim → regra acadêmica (coluna e índice). Sem FK.

SET @db := DATABASE();

SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletins' AND INDEX_NAME = 'idx_boletins_regra_academica'
);
SET @sql := IF(
  @idx_exists > 0,
  'ALTER TABLE `boletins` DROP KEY `idx_boletins_regra_academica`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletins' AND COLUMN_NAME = 'regra_academica_id'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `boletins` DROP COLUMN `regra_academica_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
