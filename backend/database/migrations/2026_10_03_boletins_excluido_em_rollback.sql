-- Rollback de 2026_10_03_boletins_excluido_em.sql
-- DROP COLUMN: a data de ocultação é descartada; as linhas de boletins permanecem.

SET @db := DATABASE();

SET @idx_exists := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletins' AND INDEX_NAME = 'idx_boletins_excluido_em'
);
SET @sql := IF(
  @idx_exists > 0,
  'ALTER TABLE `boletins` DROP KEY `idx_boletins_excluido_em`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletins' AND COLUMN_NAME = 'excluido_em'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `boletins` DROP COLUMN `excluido_em`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
