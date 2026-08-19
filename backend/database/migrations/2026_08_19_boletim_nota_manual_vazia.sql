-- Boletim: permite gravar uma sobrescrita manual de nota (tabela
-- `boletim_notas_manuais`) explicitamente "sem nota" (traço), distinto de
-- não ter sobrescrita nenhuma. Antes, `nota` era NOT NULL e a única forma de
-- "limpar" uma célula editada era remover a sobrescrita, o que fazia a
-- célula voltar a mostrar o dado real (prova/jornada) quando ele existia,
-- em vez de ficar em branco. Tenant. Idempotente.
-- Rollback: 2026_08_19_boletim_nota_manual_vazia_rollback.sql

SET @db := DATABASE();

SET @tbl := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='boletim_notas_manuais');
SET @nullable := (SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='boletim_notas_manuais' AND COLUMN_NAME='nota');
SET @sql := IF(@tbl>0 AND @nullable='NO',
  "ALTER TABLE `boletim_notas_manuais` MODIFY COLUMN `nota` DECIMAL(8,2) NULL DEFAULT NULL",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
