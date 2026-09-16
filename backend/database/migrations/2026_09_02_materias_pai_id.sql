-- Hierarquia de 1 nível em Componentes Curriculares (pai = rótulo de área, sem nota).
-- Tenant. Idempotente. Rollback: 2026_09_02_materias_pai_id_rollback.sql

SET @db := DATABASE();

SET @has_materias := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'materias'
);

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'materias' AND COLUMN_NAME = 'pai_id'
);

SET @sql := IF(
  @has_materias > 0 AND @col = 0,
  "ALTER TABLE `materias` ADD COLUMN `pai_id` INT NULL DEFAULT NULL COMMENT 'Área-rótulo (sem nota própria)' AFTER `ativo`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'materias' AND INDEX_NAME = 'idx_materias_pai_id'
);
SET @sql := IF(
  @has_materias > 0 AND @idx = 0,
  'ALTER TABLE `materias` ADD KEY `idx_materias_pai_id` (`pai_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
