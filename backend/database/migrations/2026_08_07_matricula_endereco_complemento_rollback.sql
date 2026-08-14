-- Rollback: 2026_08_07_matricula_endereco_complemento.sql

SET @db := DATABASE();
SET @has_mp := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos');
SET @has_mpr := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos_responsaveis');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='aluno_end_complemento');
SET @sql := IF(@has_mp>0 AND @col>0, "ALTER TABLE matricula_processos DROP COLUMN `aluno_end_complemento`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos_responsaveis' AND COLUMN_NAME='end_cep');
SET @sql := IF(@has_mpr>0 AND @col>0,
  "ALTER TABLE matricula_processos_responsaveis
     DROP COLUMN `end_uf`,
     DROP COLUMN `end_cidade`,
     DROP COLUMN `end_bairro`,
     DROP COLUMN `end_complemento`,
     DROP COLUMN `end_numero`,
     DROP COLUMN `end_cep`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
