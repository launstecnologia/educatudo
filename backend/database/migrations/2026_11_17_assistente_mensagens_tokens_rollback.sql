-- Rollback: 2026_11_17_assistente_mensagens_tokens.sql

SET @db := DATABASE();

SET @tem_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_mensagens' AND INDEX_NAME = 'idx_assistente_msg_tokens_dia'
);
SET @sql := IF(@tem_idx > 0,
  'ALTER TABLE `assistente_mensagens` DROP KEY `idx_assistente_msg_tokens_dia`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_modelo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_mensagens' AND COLUMN_NAME = 'modelo'
);
SET @sql := IF(@tem_modelo > 0,
  'ALTER TABLE `assistente_mensagens` DROP COLUMN `modelo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_total := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_mensagens' AND COLUMN_NAME = 'total_tokens'
);
SET @sql := IF(@tem_total > 0,
  'ALTER TABLE `assistente_mensagens` DROP COLUMN `total_tokens`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_completion := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_mensagens' AND COLUMN_NAME = 'completion_tokens'
);
SET @sql := IF(@tem_completion > 0,
  'ALTER TABLE `assistente_mensagens` DROP COLUMN `completion_tokens`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_prompt := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_mensagens' AND COLUMN_NAME = 'prompt_tokens'
);
SET @sql := IF(@tem_prompt > 0,
  'ALTER TABLE `assistente_mensagens` DROP COLUMN `prompt_tokens`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
