-- Rollback: remove materia_unica_modo de boletim_componentes

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_componentes'
    AND COLUMN_NAME = 'materia_unica_modo'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `boletim_componentes` DROP COLUMN `materia_unica_modo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
