-- A recuperação passa a guardar o aluno por matéria.
-- Quem ficou abaixo em Matemática não entra automaticamente em História.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_29_eventos_avaliativos_alunos_materia_rollback.sql

SET @db := DATABASE();
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'eventos_avaliativos_alunos'
    AND COLUMN_NAME = 'materia_id'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `eventos_avaliativos_alunos`
     ADD COLUMN `materia_id` INT NULL DEFAULT NULL
       COMMENT ''Matéria em que o aluno faz a recuperação''
     AFTER `aluno_id`',
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
  @idx > 0,
  'ALTER TABLE `eventos_avaliativos_alunos` DROP INDEX `uk_evento_aluno`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'eventos_avaliativos_alunos'
    AND INDEX_NAME = 'uk_evento_aluno_materia'
);
SET @sql := IF(
  @idx = 0,
  'ALTER TABLE `eventos_avaliativos_alunos`
     ADD UNIQUE KEY `uk_evento_aluno_materia` (`evento_id`, `aluno_id`, `materia_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
