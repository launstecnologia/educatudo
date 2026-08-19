-- Rollback: 2026_08_19_boletim_nota_manual_vazia.sql
-- Zera as sobrescritas "sem nota" (nota NULL) antes de voltar a coluna pra
-- NOT NULL, senão o ALTER falha com linhas nulas existentes.

SET @db := DATABASE();

SET @tbl := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='boletim_notas_manuais');
SET @sql := IF(@tbl>0,
  "UPDATE `boletim_notas_manuais` SET `nota` = 0 WHERE `nota` IS NULL",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @nullable := (SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='boletim_notas_manuais' AND COLUMN_NAME='nota');
SET @sql := IF(@tbl>0 AND @nullable='YES',
  "ALTER TABLE `boletim_notas_manuais` MODIFY COLUMN `nota` DECIMAL(8,2) NOT NULL",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
