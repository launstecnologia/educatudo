-- Rollback: 2026_09_09_fechamento_periodo.sql
-- Remove a máquina de estados por turma×período. Não apaga resultado_academico
-- (detalhe por aluno); só a coluna de vínculo, se existir.

SET @db := DATABASE();

SET @has_res := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_academico');
SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_academico' AND INDEX_NAME='idx_resultado_fechamento_periodo');
SET @sql := IF(@has_res>0 AND @idx>0,
  "ALTER TABLE `resultado_academico` DROP INDEX `idx_resultado_fechamento_periodo`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_academico' AND COLUMN_NAME='fechamento_periodo_id');
SET @sql := IF(@has_res>0 AND @col>0,
  "ALTER TABLE `resultado_academico` DROP COLUMN `fechamento_periodo_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `fechamento_periodo_historico`;
DROP TABLE IF EXISTS `fechamento_periodo`;
SET FOREIGN_KEY_CHECKS = 1;
