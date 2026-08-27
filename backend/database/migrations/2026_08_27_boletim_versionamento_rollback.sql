-- Rollback: 2026_08_27_boletim_versionamento.sql
-- Remove versionamento e trava por aluno. Linhas históricas em
-- boletim_resultados_gerados (vigente=0) continuam no banco — não apagamos
-- notas. Justificativa do DROP: desfaz só o que esta migration adicionou.

SET @db := DATABASE();
SET @has_res := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados'
);

SET @fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados'
    AND CONSTRAINT_NAME = 'fk_boletim_resultados_geracao' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@has_res > 0 AND @fk > 0,
  "ALTER TABLE `boletim_resultados_gerados` DROP FOREIGN KEY `fk_boletim_resultados_geracao`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados' AND INDEX_NAME = 'idx_boletim_resultados_geracao'
);
SET @sql := IF(@has_res > 0 AND @idx > 0,
  "ALTER TABLE `boletim_resultados_gerados` DROP INDEX `idx_boletim_resultados_geracao`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados' AND INDEX_NAME = 'idx_boletim_resultados_vigente'
);
SET @sql := IF(@has_res > 0 AND @idx > 0,
  "ALTER TABLE `boletim_resultados_gerados` DROP INDEX `idx_boletim_resultados_vigente`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados' AND COLUMN_NAME = 'vigente'
);
SET @sql := IF(@has_res > 0 AND @col > 0,
  "ALTER TABLE `boletim_resultados_gerados` DROP COLUMN `vigente`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados' AND COLUMN_NAME = 'versao'
);
SET @sql := IF(@has_res > 0 AND @col > 0,
  "ALTER TABLE `boletim_resultados_gerados` DROP COLUMN `versao`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados' AND COLUMN_NAME = 'geracao_id'
);
SET @sql := IF(@has_res > 0 AND @col > 0,
  "ALTER TABLE `boletim_resultados_gerados` DROP COLUMN `geracao_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `boletim_alunos_travados`;
DROP TABLE IF EXISTS `boletim_geracoes`;
