-- Rollback de 2026_06_25_instituicao_setup.sql
-- Remove declaracoes_emitidas, a coluna alunos.unidade_id (e índice) e a tabela unidades.

SET @db := DATABASE();

DROP TABLE IF EXISTS `declaracoes_emitidas`;

-- Remove índice idx_alunos_unidade (se existir)
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

-- Remove coluna unidade_id (se existir)
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

DROP TABLE IF EXISTS `unidades`;
