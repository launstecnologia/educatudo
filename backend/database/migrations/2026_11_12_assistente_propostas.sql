-- Propostas de escrita do Assistente Admin (ocorrência, entrada/saída, chamada).
-- Isolamento por banco de tenant; confirmação humana obrigatória antes de aplicar.

SET @db := DATABASE();

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='assistente_propostas');
SET @sql := IF(@has=0,
  "CREATE TABLE `assistente_propostas` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `conversa_id` INT UNSIGNED NULL,
    `mensagem_id` BIGINT UNSIGNED NULL,
    `usuario_id` INT NOT NULL COMMENT 'usuarios.id do admin autor',
    `tipo` VARCHAR(40) NOT NULL COMMENT 'criar_ocorrencia|entrada_saida|chamada_aula',
    `payload_json` JSON NOT NULL,
    `confirmado` TINYINT(1) NOT NULL DEFAULT 0,
    `confirmado_em` DATETIME NULL,
    `resultado_json` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_assistente_prop_usuario` (`usuario_id`, `confirmado`, `created_at`),
    KEY `idx_assistente_prop_conversa` (`conversa_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
