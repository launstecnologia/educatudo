-- Wizard de matrícula: origem ampliada, endereço estruturado do aluno, campos extras do responsável.
-- Tenant. Idempotente. Rollback: 2026_08_07_matricula_wizard_campos_rollback.sql

SET @db := DATABASE();

-- ── 1) Origem: incluir rede_social e outros ──────────────────────────────────
SET @has_mp := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos');
SET @sql := IF(@has_mp>0,
  "ALTER TABLE matricula_processos
   MODIFY COLUMN `origem` ENUM('interno','site','whatsapp','indicacao','evento','rede_social','outros')
   NOT NULL DEFAULT 'interno'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2) Endereço estruturado + escola anterior no processo ────────────────────
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='aluno_end_numero');
SET @sql := IF(@has_mp>0 AND @col=0,
  "ALTER TABLE matricula_processos
     ADD COLUMN `aluno_end_numero` VARCHAR(20) DEFAULT NULL AFTER `aluno_endereco`,
     ADD COLUMN `aluno_end_bairro` VARCHAR(120) DEFAULT NULL AFTER `aluno_end_numero`,
     ADD COLUMN `aluno_end_cidade` VARCHAR(120) DEFAULT NULL AFTER `aluno_end_bairro`,
     ADD COLUMN `aluno_end_uf` CHAR(2) DEFAULT NULL AFTER `aluno_end_cidade`,
     ADD COLUMN `aluno_end_cep` VARCHAR(12) DEFAULT NULL AFTER `aluno_end_uf`,
     ADD COLUMN `aluno_escola_anterior` VARCHAR(255) DEFAULT NULL AFTER `aluno_end_cep`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3) Campos extras do responsável no satélite ──────────────────────────────
SET @has_mpr := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos_responsaveis');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos_responsaveis' AND COLUMN_NAME='data_nascimento');
SET @sql := IF(@has_mpr>0 AND @col=0,
  "ALTER TABLE matricula_processos_responsaveis
     ADD COLUMN `data_nascimento` DATE DEFAULT NULL AFTER `rg`,
     ADD COLUMN `estado_civil` VARCHAR(40) DEFAULT NULL AFTER `data_nascimento`,
     ADD COLUMN `profissao` VARCHAR(120) DEFAULT NULL AFTER `estado_civil`,
     ADD COLUMN `empresa` VARCHAR(180) DEFAULT NULL AFTER `profissao`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4) Path do contrato assinado manualmente (além do PDF gerado) ────────────
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='contrato_assinado_path');
SET @sql := IF(@has_mp>0 AND @col=0,
  "ALTER TABLE matricula_processos
     ADD COLUMN `contrato_assinado_path` VARCHAR(500) DEFAULT NULL AFTER `contrato_hash`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
