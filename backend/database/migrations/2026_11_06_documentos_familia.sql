-- Documentos da família: arquivos enviados, ficha de saúde e aceite de declarações.
-- O hash guarda o texto aceito naquele momento, com data.
-- Tenant. Idempotente. Rollback: 2026_11_06_documentos_familia_rollback.sql

SET @db := DATABASE();

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='familia_documentos_arquivos');
SET @sql := IF(@has=0,
  "CREATE TABLE `familia_documentos_arquivos` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `aluno_id` INT UNSIGNED NOT NULL,
    `responsavel_id` INT UNSIGNED NOT NULL,
    `titulo` VARCHAR(180) NOT NULL,
    `arquivo` VARCHAR(500) NOT NULL,
    `arquivo_nome` VARCHAR(255) NOT NULL,
    `tipo_arquivo` VARCHAR(100) NOT NULL,
    `tamanho` INT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_familia_doc_aluno` (`aluno_id`, `id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='familia_fichas_saude');
SET @sql := IF(@has=0,
  "CREATE TABLE `familia_fichas_saude` (
    `aluno_id` INT UNSIGNED NOT NULL,
    `responsavel_id` INT UNSIGNED NOT NULL,
    `tipo_sanguineo` VARCHAR(8) NULL,
    `alergias` TEXT NULL,
    `medicamentos` TEXT NULL,
    `condicoes` TEXT NULL,
    `plano_saude` VARCHAR(180) NULL,
    `emergencia_nome` VARCHAR(180) NULL,
    `emergencia_telefone` VARCHAR(40) NULL,
    `observacoes` TEXT NULL,
    `hash_registro` CHAR(64) NOT NULL,
    `atualizado_em` DATETIME NOT NULL,
    PRIMARY KEY (`aluno_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='familia_declaracoes');
SET @sql := IF(@has=0,
  "CREATE TABLE `familia_declaracoes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `titulo` VARCHAR(180) NOT NULL,
    `texto` TEXT NOT NULL,
    `versao` INT UNSIGNED NOT NULL DEFAULT 1,
    `ativa` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='familia_declaracao_aceites');
SET @sql := IF(@has=0,
  "CREATE TABLE `familia_declaracao_aceites` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `declaracao_id` BIGINT UNSIGNED NOT NULL,
    `aluno_id` INT UNSIGNED NOT NULL,
    `responsavel_id` INT UNSIGNED NOT NULL,
    `versao` INT UNSIGNED NOT NULL,
    `texto_hash` CHAR(64) NOT NULL,
    `aceito_em` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_familia_aceite` (`declaracao_id`, `aluno_id`, `responsavel_id`, `versao`),
    KEY `idx_familia_aceite_aluno` (`aluno_id`, `declaracao_id`),
    CONSTRAINT `fk_familia_aceite_declaracao` FOREIGN KEY (`declaracao_id`) REFERENCES `familia_declaracoes` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
