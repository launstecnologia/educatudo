-- Rollback de 2026_10_28_recuperacao_escolha.sql

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'recuperacoes'
    AND COLUMN_NAME = 'permite_aumentar'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `recuperacoes` DROP COLUMN `permite_aumentar`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'recuperacoes'
    AND COLUMN_NAME = 'formula'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `recuperacoes` DROP COLUMN `formula`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'recuperacoes'
    AND COLUMN_NAME = 'aplica_em'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `recuperacoes` DROP COLUMN `aplica_em`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
