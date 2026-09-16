-- Rollback de 2026_09_02_materias_pai_id.sql (remove só o que essa migration adicionou).

SET @db := DATABASE();

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'materias' AND INDEX_NAME = 'idx_materias_pai_id'
);
SET @sql := IF(
  @idx > 0,
  'ALTER TABLE `materias` DROP KEY `idx_materias_pai_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'materias' AND COLUMN_NAME = 'pai_id'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `materias` DROP COLUMN `pai_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
