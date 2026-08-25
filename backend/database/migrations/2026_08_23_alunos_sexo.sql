-- Garante alunos.sexo (lista de chamada / Censo).
-- A 059_lista_chamada.sql pode ter sido marcada como executada sem o ALTER
-- (falha no ADD COLUMN abortava o restante em versões antigas, ou o dump
-- já tinha as tabelas de chamada). Idempotente.
-- Rollback: 2026_08_23_alunos_sexo_rollback.sql

SET @db := DATABASE();

SET @col_sexo := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'alunos'
    AND COLUMN_NAME = 'sexo'
);
SET @sql_sexo := IF(
  @col_sexo > 0,
  'SELECT 1',
  "ALTER TABLE `alunos` ADD COLUMN `sexo` ENUM('M','F','N') NULL DEFAULT NULL"
);
PREPARE stmt_sexo FROM @sql_sexo;
EXECUTE stmt_sexo;
DEALLOCATE PREPARE stmt_sexo;
