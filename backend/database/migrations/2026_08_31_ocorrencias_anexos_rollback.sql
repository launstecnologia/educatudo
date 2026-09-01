-- Rollback de 2026_08_31_ocorrencias_anexos.sql
-- DROP da tabela de anexos: só existia nesta migration; os arquivos em disco
-- não são apagados automaticamente (podem ser limpos depois no storage).

SET @db := DATABASE();

SET @fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'ocorrencias_anexos'
    AND CONSTRAINT_NAME = 'fk_ocorrencias_anexos_ocorrencia' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk := IF(
  @fk > 0,
  'ALTER TABLE `ocorrencias_anexos` DROP FOREIGN KEY `fk_ocorrencias_anexos_ocorrencia`',
  'SELECT 1'
);
PREPARE stmt_fk_rb FROM @sql_fk;
EXECUTE stmt_fk_rb;
DEALLOCATE PREPARE stmt_fk_rb;

DROP TABLE IF EXISTS `ocorrencias_anexos`;

SET @col_testemunhas := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos_ocorrencias' AND COLUMN_NAME = 'testemunhas'
);
SET @sql_testemunhas := IF(
  @col_testemunhas > 0,
  'ALTER TABLE `alunos_ocorrencias` DROP COLUMN `testemunhas`',
  'SELECT 1'
);
PREPARE stmt_testemunhas_rb FROM @sql_testemunhas;
EXECUTE stmt_testemunhas_rb;
DEALLOCATE PREPARE stmt_testemunhas_rb;
