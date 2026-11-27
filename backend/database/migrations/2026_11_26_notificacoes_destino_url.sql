-- Deep link do sino: ao clicar na notificação, abre a tela de origem.
-- Tenant. Idempotente. Rollback: 2026_11_26_notificacoes_destino_url_rollback.sql

SET @db := DATABASE();

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'notificacoes' AND COLUMN_NAME = 'destino_url'
);
SET @sql := IF(@col = 0,
    "ALTER TABLE `notificacoes`
        ADD COLUMN `destino_url` VARCHAR(500) NULL DEFAULT NULL
        COMMENT 'Caminho relativo interno (ex. /admin/comunicacao-escolar/fale-com-a-escola/12)'
        AFTER `mural_recado_id`",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
