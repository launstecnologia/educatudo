-- Evento de faltas: matérias fixas em colunas (tabela totais do bimestre).
-- Idempotente; SchoolAbsence::ensureSchema() também adiciona a coluna em runtime.

SET @db := DATABASE();
SET @has_table := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'faltas_eventos'
);

SET @has_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'faltas_eventos'
    AND COLUMN_NAME = 'materias_json'
);
SET @sql := IF(
  @has_table = 0 OR @has_col > 0,
  'SELECT 1',
  'ALTER TABLE faltas_eventos ADD COLUMN materias_json TEXT NULL COMMENT ''IDs matérias colunas; NULL=grade horária'' AFTER turmas_json'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
