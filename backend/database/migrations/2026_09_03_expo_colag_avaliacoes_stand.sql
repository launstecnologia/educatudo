-- Expo Colag: avaliações públicas dos visitantes via QR do stand.
-- Sem escola_id: isolamento por PDO/tenant.

CREATE TABLE IF NOT EXISTS `expo_colag_stand_avaliacoes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `stand_id` INT UNSIGNED NOT NULL,
    `projeto_id` INT UNSIGNED NOT NULL,
    `nota` TINYINT UNSIGNED NOT NULL,
    `mensagem` VARCHAR(500) NULL,
    `ip_hash` CHAR(64) NULL,
    `user_agent` VARCHAR(255) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_avaliacoes_stand` (`stand_id`),
    KEY `idx_expo_colag_avaliacoes_projeto` (`projeto_id`),
    KEY `idx_expo_colag_avaliacoes_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
