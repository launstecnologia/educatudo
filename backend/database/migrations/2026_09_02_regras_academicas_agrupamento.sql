-- Aprovação da área: regra acadêmica aponta para um agrupamento de componentes.
-- Tenant. Idempotente. Rollback: 2026_09_02_regras_academicas_agrupamento_rollback.sql

SET @db := DATABASE();

SET @has_regras := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas'
);

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND COLUMN_NAME = 'agrupamento_id'
);

SET @sql := IF(
  @has_regras > 0 AND @col = 0,
  "ALTER TABLE `regras_academicas` ADD COLUMN `agrupamento_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Aprovação pela média do agrupamento' AFTER `materia_id`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND COLUMN_NAME = 'agrupamento_id'
);
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND INDEX_NAME = 'idx_regras_academicas_agrupamento'
);
SET @sql := IF(
  @idx > 0 AND @idx_exists = 0,
  'ALTER TABLE `regras_academicas` ADD KEY `idx_regras_academicas_agrupamento` (`agrupamento_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
