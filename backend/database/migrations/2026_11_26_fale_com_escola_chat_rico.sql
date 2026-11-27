-- Fale com a Escola: anexos (vídeo/arquivo) + curtidas estilo chat.
-- Tenant. Idempotente. Rollback: 2026_11_26_fale_com_escola_chat_rico_rollback.sql

SET @db := DATABASE();

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'familia_mensagens' AND COLUMN_NAME = 'anexo_tipo'
);
SET @sql := IF(@col = 0,
    "ALTER TABLE `familia_mensagens`
        ADD COLUMN `anexo_tipo` ENUM('imagem','video','arquivo') NULL DEFAULT NULL
            COMMENT 'Tipo do anexo; imagem legado também usa coluna imagem'
            AFTER `imagem_nome`,
        ADD COLUMN `anexo_mime` VARCHAR(120) NULL DEFAULT NULL AFTER `anexo_tipo`",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill: mensagens com imagem antiga viram anexo_tipo=imagem
UPDATE `familia_mensagens`
SET `anexo_tipo` = 'imagem',
    `anexo_mime` = COALESCE(`anexo_mime`, 'image/jpeg')
WHERE `imagem` IS NOT NULL AND `imagem` <> '' AND (`anexo_tipo` IS NULL OR `anexo_tipo` = '');

SET @has := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'familia_mensagem_reacoes'
);
SET @sql := IF(@has = 0,
    "CREATE TABLE `familia_mensagem_reacoes` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `mensagem_id` BIGINT UNSIGNED NOT NULL,
        `autor_tipo` ENUM('responsavel','escola') NOT NULL,
        `autor_id` INT UNSIGNED NOT NULL,
        `reacao` VARCHAR(32) NOT NULL DEFAULT 'curtir',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_familia_reacao_autor` (`mensagem_id`, `autor_tipo`, `autor_id`, `reacao`),
        KEY `idx_familia_reacao_mensagem` (`mensagem_id`),
        CONSTRAINT `fk_familia_reacao_mensagem`
            FOREIGN KEY (`mensagem_id`) REFERENCES `familia_mensagens` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
