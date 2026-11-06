-- Canal Fale com a Escola: a família inicia a conversa e a escola responde,
-- com texto e imagem. Também guarda imagem na resposta de Fique sabendo.
-- Tenant. Idempotente. Rollback: 2026_11_06_fale_com_a_escola_rollback.sql

SET @db := DATABASE();

SET @has := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'familia_conversas'
);
SET @sql := IF(@has = 0,
    "CREATE TABLE `familia_conversas` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `aluno_id` INT UNSIGNED NOT NULL,
        `responsavel_id` INT UNSIGNED NOT NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uk_familia_conversa_aluno_responsavel` (`aluno_id`, `responsavel_id`),
        KEY `idx_familia_conversa_atualizada` (`updated_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'familia_mensagens'
);
SET @sql := IF(@has = 0,
    "CREATE TABLE `familia_mensagens` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `conversa_id` BIGINT UNSIGNED NOT NULL,
        `autor_tipo` ENUM('responsavel','escola') NOT NULL,
        `autor_id` INT UNSIGNED NOT NULL,
        `mensagem` TEXT NULL,
        `imagem` VARCHAR(500) NULL,
        `imagem_nome` VARCHAR(255) NULL,
        `lido_escola_em` DATETIME NULL,
        `lido_familia_em` DATETIME NULL,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_familia_mensagem_conversa` (`conversa_id`, `id`),
        CONSTRAINT `fk_familia_mensagem_conversa` FOREIGN KEY (`conversa_id`) REFERENCES `familia_conversas` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'school_communication_replies' AND COLUMN_NAME = 'imagem'
);
SET @sql := IF(@has = 0,
    "ALTER TABLE `school_communication_replies`
        ADD COLUMN `imagem` VARCHAR(500) NULL AFTER `mensagem`,
        ADD COLUMN `imagem_nome` VARCHAR(255) NULL AFTER `imagem`",
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
