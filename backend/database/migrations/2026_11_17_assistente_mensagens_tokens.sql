-- Tokens de input/output por mensagem da Tudinha (assistente admin).
-- Tenant. Idempotente. Rollback: 2026_11_17_assistente_mensagens_tokens_rollback.sql

SET @db := DATABASE();

SET @tem_tabela := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_mensagens'
);

SET @tem_prompt := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_mensagens' AND COLUMN_NAME = 'prompt_tokens'
);
SET @sql := IF(@tem_tabela = 1 AND @tem_prompt = 0,
  'ALTER TABLE `assistente_mensagens`
     ADD COLUMN `prompt_tokens` INT UNSIGNED NOT NULL DEFAULT 0
       COMMENT ''Tokens de input (prompt) desta resposta''
     AFTER `painel_json`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_completion := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_mensagens' AND COLUMN_NAME = 'completion_tokens'
);
SET @sql := IF(@tem_tabela = 1 AND @tem_completion = 0,
  'ALTER TABLE `assistente_mensagens`
     ADD COLUMN `completion_tokens` INT UNSIGNED NOT NULL DEFAULT 0
       COMMENT ''Tokens de output (completion) desta resposta''
     AFTER `prompt_tokens`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_total := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_mensagens' AND COLUMN_NAME = 'total_tokens'
);
SET @sql := IF(@tem_tabela = 1 AND @tem_total = 0,
  'ALTER TABLE `assistente_mensagens`
     ADD COLUMN `total_tokens` INT UNSIGNED NOT NULL DEFAULT 0
       COMMENT ''Soma input+output desta resposta''
     AFTER `completion_tokens`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_modelo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_mensagens' AND COLUMN_NAME = 'modelo'
);
SET @sql := IF(@tem_tabela = 1 AND @tem_modelo = 0,
  'ALTER TABLE `assistente_mensagens`
     ADD COLUMN `modelo` VARCHAR(80) NULL
       COMMENT ''Modelo LLM usado nesta resposta''
     AFTER `total_tokens`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_mensagens' AND INDEX_NAME = 'idx_assistente_msg_tokens_dia'
);
SET @sql := IF(@tem_tabela = 1 AND @tem_idx = 0,
  'ALTER TABLE `assistente_mensagens`
     ADD KEY `idx_assistente_msg_tokens_dia` (`created_at`, `role`, `total_tokens`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
