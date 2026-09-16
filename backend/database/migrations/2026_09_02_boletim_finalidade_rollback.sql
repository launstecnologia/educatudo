-- Rollback de 2026_09_02_boletim_finalidade.sql

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_regras' AND COLUMN_NAME = 'finalidade'
);

SET @sql := IF(
  @col > 0,
  "ALTER TABLE `boletim_regras` DROP COLUMN `finalidade`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
