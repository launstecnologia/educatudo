-- Carteirinha escolar: a escola liga ou desliga o uso, e cada emissão
-- tem um token aleatório para o QR de validação pública.
-- Tenant. Idempotente. Rollback: 2026_11_03_carteirinha_rollback.sql

SET @db := DATABASE();

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='carteirinha_config');
SET @sql := IF(@has=0,
  "CREATE TABLE `carteirinha_config` (
    `id` TINYINT UNSIGNED NOT NULL,
    `habilitada` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `carteirinha_config` (`id`, `habilitada`) VALUES (1, 0);

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='carteirinhas');
SET @sql := IF(@has=0,
  "CREATE TABLE `carteirinhas` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `aluno_id` INT NOT NULL,
    `ano_letivo_id` INT DEFAULT NULL,
    `token` CHAR(32) NOT NULL,
    `status` ENUM('emitida','cancelada') NOT NULL DEFAULT 'emitida',
    `nome_exibicao` VARCHAR(180) NOT NULL,
    `ra` VARCHAR(40) DEFAULT NULL,
    `turma_nome` VARCHAR(120) DEFAULT NULL,
    `foto_url` TEXT DEFAULT NULL,
    `emitida_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `valida_ate` DATE NOT NULL,
    `cancelada_em` DATETIME DEFAULT NULL,
    `motivo_cancelamento` VARCHAR(180) DEFAULT NULL,
    `emitida_por` INT DEFAULT NULL,
    `versao` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_carteirinha_token` (`token`),
    KEY `idx_carteirinha_aluno_status` (`aluno_id`, `status`),
    CONSTRAINT `fk_carteirinha_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
