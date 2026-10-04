-- Exclusão do modelo de boletim só oculta a linha na listagem.
-- Tenant. Idempotente. Rollback: 2026_10_03_boletins_excluido_em_rollback.sql

SET @db := DATABASE();

SET @has_boletins := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletins'
);

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletins' AND COLUMN_NAME = 'excluido_em'
);

SET @sql := IF(
  @has_boletins > 0 AND @col = 0,
  "ALTER TABLE `boletins` ADD COLUMN `excluido_em` DATETIME NULL DEFAULT NULL COMMENT 'Oculto da listagem. O cadastro permanece' AFTER `ativo`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletins' AND COLUMN_NAME = 'excluido_em'
);
SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletins' AND INDEX_NAME = 'idx_boletins_excluido_em'
);
SET @sql := IF(
  @col > 0 AND @idx_exists = 0,
  'ALTER TABLE `boletins` ADD KEY `idx_boletins_excluido_em` (`excluido_em`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
