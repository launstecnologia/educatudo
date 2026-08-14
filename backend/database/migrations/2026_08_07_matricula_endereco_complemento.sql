-- Complemento do aluno + endereço estruturado do responsável (wizard).
-- Tenant. Idempotente. Rollback: 2026_08_07_matricula_endereco_complemento_rollback.sql

SET @db := DATABASE();
SET @has_mp := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos');
SET @has_mpr := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos_responsaveis');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='aluno_end_complemento');
SET @sql := IF(@has_mp>0 AND @col=0,
  "ALTER TABLE matricula_processos ADD COLUMN `aluno_end_complemento` VARCHAR(120) DEFAULT NULL AFTER `aluno_end_numero`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos_responsaveis' AND COLUMN_NAME='end_cep');
SET @sql := IF(@has_mpr>0 AND @col=0,
  "ALTER TABLE matricula_processos_responsaveis
     ADD COLUMN `end_cep` VARCHAR(12) DEFAULT NULL AFTER `endereco`,
     ADD COLUMN `end_numero` VARCHAR(20) DEFAULT NULL AFTER `end_cep`,
     ADD COLUMN `end_complemento` VARCHAR(120) DEFAULT NULL AFTER `end_numero`,
     ADD COLUMN `end_bairro` VARCHAR(120) DEFAULT NULL AFTER `end_complemento`,
     ADD COLUMN `end_cidade` VARCHAR(120) DEFAULT NULL AFTER `end_bairro`,
     ADD COLUMN `end_uf` CHAR(2) DEFAULT NULL AFTER `end_cidade`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
