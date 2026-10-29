-- Rollback de 2026_10_29_curso_cor.sql
-- Remove a cor do curso. O calendário letivo volta à cor do tema.

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'curso'
    AND COLUMN_NAME = 'cor'
);
SET @sql := IF(
  @col_exists > 0,
  'ALTER TABLE `curso` DROP COLUMN `cor`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
