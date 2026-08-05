-- Migration: Documentos / checklist de entrega do aluno (Fase 3)

CREATE TABLE IF NOT EXISTS `alunos_documentos` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `aluno_id` INT(11) NOT NULL,
    `tipo` VARCHAR(50) NOT NULL,
    `titulo` VARCHAR(160) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pendente',
    `arquivo_key` VARCHAR(255) NULL,
    `arquivo_nome` VARCHAR(255) NULL,
    `arquivo_mime` VARCHAR(100) NULL,
    `arquivo_tamanho` INT(11) NULL,
    `observacao` VARCHAR(255) NULL,
    `entregue_em` DATETIME NULL,
    `created_by` INT(11) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_alunos_documentos_aluno` (`aluno_id`),
    KEY `idx_alunos_documentos_aluno_tipo` (`aluno_id`, `tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
