-- Rollback: remove o estado estruturado da conversa do Assistente.
-- Tenant. Idempotente.

SET @db := DATABASE();

SET @tem_col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_conversas' AND COLUMN_NAME = 'estado_json'
);
SET @sql := IF(@tem_col > 0,
  'ALTER TABLE `assistente_conversas` DROP COLUMN `estado_json`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
