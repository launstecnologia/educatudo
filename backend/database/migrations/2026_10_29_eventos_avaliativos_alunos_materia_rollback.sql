-- Rollback de 2026_10_29_eventos_avaliativos_alunos_materia.sql
-- Falha no UNIQUE final se o mesmo aluno já estiver em duas matérias do evento:
-- a coluna materia_id já terá sido removida (DDL não desfaz).

SET @db := DATABASE();

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'eventos_avaliativos_alunos'
    AND INDEX_NAME = 'uk_evento_aluno_materia'
);
SET @sql := IF(
  @idx > 0,
  'ALTER TABLE `eventos_avaliativos_alunos` DROP INDEX `uk_evento_aluno_materia`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'eventos_avaliativos_alunos'
    AND COLUMN_NAME = 'materia_id'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `eventos_avaliativos_alunos` DROP COLUMN `materia_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'eventos_avaliativos_alunos'
    AND INDEX_NAME = 'uk_evento_aluno'
);
SET @sql := IF(
  @idx = 0,
  'ALTER TABLE `eventos_avaliativos_alunos`
     ADD UNIQUE KEY `uk_evento_aluno` (`evento_id`, `aluno_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
