-- Expo Colag: pedidos de material do aluno para o professor.
-- Tenant. Idempotente. Rollback: 2026_09_03_expo_colag_pedidos_materiais_rollback.sql

CREATE TABLE IF NOT EXISTS `expo_colag_pedidos_materiais` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `aluno_id` INT UNSIGNED NOT NULL,
    `inscricao_id` INT UNSIGNED NOT NULL,
    `titulo` VARCHAR(255) NOT NULL,
    `quantidade` VARCHAR(60) NULL,
    `observacao` VARCHAR(500) NULL,
    `status` ENUM('Pendente','Aprovado','Recusado') NOT NULL DEFAULT 'Pendente',
    `resposta_professor` VARCHAR(500) NULL,
    `decidido_por` INT UNSIGNED NULL,
    `decidido_em` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_pedidos_projeto_status` (`projeto_id`, `status`),
    KEY `idx_expo_colag_pedidos_aluno_projeto` (`aluno_id`, `projeto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
