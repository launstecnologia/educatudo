-- Rollback de 2026_09_02_boletins.sql

SET @db := DATABASE();

SET @idx_boletim := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_regras' AND INDEX_NAME = 'idx_boletim_regras_boletim'
);
SET @sql := IF(@idx_boletim > 0, 'ALTER TABLE `boletim_regras` DROP KEY `idx_boletim_regras_boletim`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_boletim := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_regras' AND COLUMN_NAME = 'boletim_id'
);
SET @sql := IF(@col_boletim > 0, 'ALTER TABLE `boletim_regras` DROP COLUMN `boletim_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_boletins := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletins'
);
SET @sql := IF(@has_boletins > 0, 'DROP TABLE `boletins`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
