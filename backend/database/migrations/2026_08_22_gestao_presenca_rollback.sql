-- Reverte 2026_08_22_gestao_presenca.sql
-- DROP justificado: tabelas novas da Gestão de Presença, sem dado legado.
-- Não reverte o ENUM saida_antecipada (migration 2026_08_19_diario_fechamento).

SET @db := DATABASE();

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='presenca_eventos');
SET @sql := IF(@has>0, 'DROP TABLE `presenca_eventos`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='presenca_identificadores');
SET @sql := IF(@has>0, 'DROP TABLE `presenca_identificadores`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='presenca_integracoes');
SET @sql := IF(@has>0, 'DROP TABLE `presenca_integracoes`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='presenca_config');
SET @sql := IF(@has>0, 'DROP TABLE `presenca_config`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_frequencias' AND COLUMN_NAME='origem');
SET @sql := IF(@col>0, 'ALTER TABLE `diario_frequencias` DROP COLUMN `origem`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='faltas_eventos' AND COLUMN_NAME='origem');
SET @sql := IF(@col>0, 'ALTER TABLE `faltas_eventos` DROP COLUMN `origem`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
