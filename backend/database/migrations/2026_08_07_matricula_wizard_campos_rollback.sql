-- Rollback: 2026_08_07_matricula_wizard_campos.sql

SET @db := DATABASE();
SET @has_mp := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos');
SET @has_mpr := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos_responsaveis');

-- Remove colunas do processo (se existirem)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='contrato_assinado_path');
SET @sql := IF(@has_mp>0 AND @col>0, "ALTER TABLE matricula_processos DROP COLUMN `contrato_assinado_path`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='aluno_escola_anterior');
SET @sql := IF(@has_mp>0 AND @col>0,
  "ALTER TABLE matricula_processos
     DROP COLUMN `aluno_escola_anterior`,
     DROP COLUMN `aluno_end_cep`,
     DROP COLUMN `aluno_end_uf`,
     DROP COLUMN `aluno_end_cidade`,
     DROP COLUMN `aluno_end_bairro`,
     DROP COLUMN `aluno_end_numero`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Reverte ENUM origem (valores novos viram interno se houver)
SET @sql := IF(@has_mp>0,
  "UPDATE matricula_processos SET origem='interno' WHERE origem IN ('rede_social','outros')",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_mp>0,
  "ALTER TABLE matricula_processos
   MODIFY COLUMN `origem` ENUM('interno','site','whatsapp','indicacao','evento')
   NOT NULL DEFAULT 'interno'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Remove colunas do responsável
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos_responsaveis' AND COLUMN_NAME='data_nascimento');
SET @sql := IF(@has_mpr>0 AND @col>0,
  "ALTER TABLE matricula_processos_responsaveis
     DROP COLUMN `empresa`,
     DROP COLUMN `profissao`,
     DROP COLUMN `estado_civil`,
     DROP COLUMN `data_nascimento`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
