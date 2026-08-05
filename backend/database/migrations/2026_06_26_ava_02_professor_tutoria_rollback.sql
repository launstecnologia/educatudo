-- Rollback (2026_06_26_ava_02_professor_tutoria.sql)
SET @db := DATABASE();
SET @has_col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professores' AND COLUMN_NAME = 'pode_tutoria'
);
SET @sql := IF(
  @has_col > 0,
  'ALTER TABLE professores DROP COLUMN pode_tutoria',
  'SELECT 1'
);
PREPARE stmt_ava_tutoria_rb FROM @sql;
EXECUTE stmt_ava_tutoria_rb;
DEALLOCATE PREPARE stmt_ava_tutoria_rb;
