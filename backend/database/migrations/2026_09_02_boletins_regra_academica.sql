-- Boletim aponta para a regra acadêmica (mínima, arredondamento, frequência).
-- Tenant. Idempotente. Rollback: 2026_09_02_boletins_regra_academica_rollback.sql

SET @db := DATABASE();

SET @has_boletins := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletins'
);

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletins' AND COLUMN_NAME = 'regra_academica_id'
);

SET @sql := IF(
  @has_boletins > 0 AND @col = 0,
  "ALTER TABLE `boletins` ADD COLUMN `regra_academica_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Regra acadêmica (mínima/aprovação)' AFTER `regra_id`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletins' AND COLUMN_NAME = 'regra_academica_id'
);
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletins' AND INDEX_NAME = 'idx_boletins_regra_academica'
);
SET @sql := IF(
  @col > 0 AND @idx_exists = 0,
  'ALTER TABLE `boletins` ADD KEY `idx_boletins_regra_academica` (`regra_academica_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
