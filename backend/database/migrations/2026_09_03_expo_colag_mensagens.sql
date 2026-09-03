-- Expo Colag: conversa do grupo (professor ↔ alunos aprovados).
-- Tenant. Idempotente. Rollback: 2026_09_03_expo_colag_mensagens_rollback.sql

CREATE TABLE IF NOT EXISTS `expo_colag_mensagens` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `autor_tipo` ENUM('professor','aluno') NOT NULL,
    `autor_id` INT UNSIGNED NOT NULL,
    `mensagem` VARCHAR(2000) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_msg_projeto` (`projeto_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
