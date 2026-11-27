-- Rollback: remove destino_url das notificações internas.
-- Tenant. Idempotente.

SET @db := DATABASE();

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'notificacoes' AND COLUMN_NAME = 'destino_url'
);
SET @sql := IF(@col > 0,
    'ALTER TABLE `notificacoes` DROP COLUMN `destino_url`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
