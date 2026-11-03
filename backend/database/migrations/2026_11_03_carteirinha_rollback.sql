-- Rollback de 2026_11_03_carteirinha.sql
-- Apaga emissões e a configuração de uso da carteirinha nesta escola.

SET @db := DATABASE();

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='carteirinhas');
SET @sql := IF(@has>0, 'DROP TABLE `carteirinhas`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='carteirinha_config');
SET @sql := IF(@has>0, 'DROP TABLE `carteirinha_config`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
