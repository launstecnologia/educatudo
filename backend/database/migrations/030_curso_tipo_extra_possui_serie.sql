-- Curso: suporte a tipo (regular/extra) e possui_serie para cursos extras sem série.
-- Cursos extras: Música, Robótica, etc. — turma com serie_id NULL.

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'curso'
    AND COLUMN_NAME = 'tipo'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `curso` ADD COLUMN `tipo` ENUM(''regular'',''extra'') NOT NULL DEFAULT ''regular'' COMMENT ''regular=com série; extra=livre'' AFTER `nome`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'curso'
    AND COLUMN_NAME = 'possui_serie'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `curso` ADD COLUMN `possui_serie` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''0=curso extra sem série'' AFTER `tipo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
