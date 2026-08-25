-- Rollback de 2026_08_22_alunos_unidade_id.sql
-- Remove só o índice e a coluna adicionados por esta migration.
-- Não dropa a tabela unidades (ela pertence a 2026_06_25_unidades_escola.sql).

SET @db := DATABASE();

SET @has_idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'alunos'
    AND INDEX_NAME = 'idx_alunos_unidade'
);
SET @sql := IF(
  @has_idx > 0,
  'ALTER TABLE alunos DROP INDEX idx_alunos_unidade',
  'SELECT 1'
);
PREPARE stmt_rb_idx FROM @sql;
EXECUTE stmt_rb_idx;
DEALLOCATE PREPARE stmt_rb_idx;

SET @has_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'alunos'
    AND COLUMN_NAME = 'unidade_id'
);
SET @sql := IF(
  @has_col > 0,
  'ALTER TABLE alunos DROP COLUMN unidade_id',
  'SELECT 1'
);
PREPARE stmt_rb_col FROM @sql;
EXECUTE stmt_rb_col;
DEALLOCATE PREPARE stmt_rb_col;
