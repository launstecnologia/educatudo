-- Faltas por matéria: uma linha por (evento, aluno, matéria).
-- Linhas antigas (sem matéria) ficam com materia_id = 0.
-- Idempotente: escolas criadas via SchoolAbsence::ensureSchema() já têm a coluna e os índices.

SET @db := DATABASE();
SET @has_table := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'faltas_lancamentos'
);

-- 1) Coluna materia_id (só se não existir)
SET @has_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'faltas_lancamentos'
    AND COLUMN_NAME = 'materia_id'
);
SET @sql := IF(
  @has_table = 0 OR @has_col > 0,
  'SELECT 1',
  'ALTER TABLE faltas_lancamentos ADD COLUMN materia_id INT NOT NULL DEFAULT 0 AFTER aluno_id'
);
PREPARE stmt_faltas_col FROM @sql;
EXECUTE stmt_faltas_col;
DEALLOCATE PREPARE stmt_faltas_col;

-- 2) Novo UNIQUE (evento, aluno, matéria) ANTES de dropar o índice antigo:
--    a FK fk_faltas_lanc_evento (evento_id) usa um índice cujo prefixo é evento_id;
--    sem isso, DROP INDEX uk_faltas_evento_aluno falha com erro 1553.

SET @has_new_uk := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'faltas_lancamentos'
    AND INDEX_NAME = 'uk_faltas_evento_aluno_materia'
);
SET @sql := IF(
  @has_table = 0 OR @has_new_uk > 0,
  'SELECT 1',
  'ALTER TABLE faltas_lancamentos ADD UNIQUE KEY uk_faltas_evento_aluno_materia (evento_id, aluno_id, materia_id)'
);
PREPARE stmt_faltas_newuk FROM @sql;
EXECUTE stmt_faltas_newuk;
DEALLOCATE PREPARE stmt_faltas_newuk;

-- 3) Remove índice antigo (evento, aluno) apenas se ainda existir
SET @has_old_uk := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'faltas_lancamentos'
    AND INDEX_NAME = 'uk_faltas_evento_aluno'
);
SET @sql := IF(
  @has_table > 0 AND @has_old_uk > 0,
  'ALTER TABLE faltas_lancamentos DROP INDEX uk_faltas_evento_aluno',
  'SELECT 1'
);
PREPARE stmt_faltas_dropuk FROM @sql;
EXECUTE stmt_faltas_dropuk;
DEALLOCATE PREPARE stmt_faltas_dropuk;

-- 4) Índice por matéria — só se não existir
SET @has_idx_mat := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'faltas_lancamentos'
    AND INDEX_NAME = 'idx_faltas_lanc_materia'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx_mat > 0,
  'SELECT 1',
  'ALTER TABLE faltas_lancamentos ADD KEY idx_faltas_lanc_materia (materia_id)'
);
PREPARE stmt_faltas_idx FROM @sql;
EXECUTE stmt_faltas_idx;
DEALLOCATE PREPARE stmt_faltas_idx;
