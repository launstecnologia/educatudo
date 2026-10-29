-- Rollback de 2026_10_28_recuperacao_por_regra.sql
-- Tira a coluna. As linhas voltam a valer pelo ano, como antes.
-- Não mexe em deleted_at: exclusão feita pelo usuário permanece.

SET @db := DATABASE();

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'recuperacoes'
    AND INDEX_NAME = 'idx_recuperacoes_regra'
);
SET @sql := IF(
  @idx > 0,
  'ALTER TABLE `recuperacoes` DROP KEY `idx_recuperacoes_regra`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'recuperacoes'
    AND COLUMN_NAME = 'regra_id'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `recuperacoes` DROP COLUMN `regra_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
