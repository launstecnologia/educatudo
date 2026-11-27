-- Fale com a Escola: resposta citada (estilo WhatsApp).
-- Tenant. Idempotente. Rollback: 2026_11_26_fale_com_escola_resposta_citada_rollback.sql

SET @db := DATABASE();

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'familia_mensagens' AND COLUMN_NAME = 'resposta_a_mensagem_id'
);
SET @sql := IF(@col = 0,
    "ALTER TABLE `familia_mensagens`
        ADD COLUMN `resposta_a_mensagem_id` BIGINT UNSIGNED NULL DEFAULT NULL
            COMMENT 'Mensagem citada nesta resposta (estilo WhatsApp)'
            AFTER `autor_id`,
        ADD KEY `idx_familia_msg_resposta` (`resposta_a_mensagem_id`),
        ADD CONSTRAINT `fk_familia_msg_resposta`
            FOREIGN KEY (`resposta_a_mensagem_id`) REFERENCES `familia_mensagens` (`id`)
            ON DELETE SET NULL",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
