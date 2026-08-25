-- Garante alunos.unidade_id + índice (vínculo N:1 com unidades).
-- A migration 2026_06_25_unidades_escola.sql / instituicao_setup.sql pode ter
-- sido marcada como executada sem criar a coluna (CREATE TABLE unidades
-- provoca commit implícito e o UPDATE final com WHERE unidade_id falhava
-- com 1054, e a listagem de Instituição quebrava ao contar alunos).
-- Idempotente. Sem AFTER turma_id para não depender da posição da coluna.
-- Rollback: 2026_08_22_alunos_unidade_id_rollback.sql

SET @db := DATABASE();

SET @has_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'alunos'
    AND COLUMN_NAME = 'unidade_id'
);
SET @sql := IF(
  @has_col > 0,
  'SELECT 1',
  'ALTER TABLE alunos ADD COLUMN unidade_id INT(11) NULL'
);
PREPARE stmt_unidade_col FROM @sql;
EXECUTE stmt_unidade_col;
DEALLOCATE PREPARE stmt_unidade_col;

SET @has_idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'alunos'
    AND INDEX_NAME = 'idx_alunos_unidade'
);
SET @sql := IF(
  @has_idx > 0,
  'SELECT 1',
  'ALTER TABLE alunos ADD KEY idx_alunos_unidade (unidade_id)'
);
PREPARE stmt_unidade_idx FROM @sql;
EXECUTE stmt_unidade_idx;
DEALLOCATE PREPARE stmt_unidade_idx;

SET @tem_unidades := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'unidades'
);
SET @has_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'alunos'
    AND COLUMN_NAME = 'unidade_id'
);
SET @sql := IF(
  @has_col > 0 AND @tem_unidades > 0,
  'UPDATE alunos SET unidade_id = (SELECT MIN(id) FROM unidades) WHERE unidade_id IS NULL AND EXISTS (SELECT 1 FROM unidades)',
  'SELECT 1'
);
PREPARE stmt_unidade_backfill FROM @sql;
EXECUTE stmt_unidade_backfill;
DEALLOCATE PREPARE stmt_unidade_backfill;
