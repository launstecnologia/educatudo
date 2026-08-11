-- Expo Colag S2 — relações do projeto (wizard) + autorização de imagem.
-- Sem escola_id (isolamento por PDO).

CREATE TABLE IF NOT EXISTS `expo_colag_projeto_tipos_trabalho` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `tipo` VARCHAR(120) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_tipos_projeto` (`projeto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_projeto_papeis` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `nome` VARCHAR(120) NOT NULL,
    `descricao` TEXT NULL,
    `vagas` INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_papeis_projeto` (`projeto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_projeto_habilidades` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `codigo_habilidade` VARCHAR(40) NOT NULL,
    `habilidade_id` INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_expo_colag_hab` (`projeto_id`, `codigo_habilidade`),
    KEY `idx_expo_colag_hab_codigo` (`codigo_habilidade`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_projeto_encontros` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `rotulo` VARCHAR(180) NOT NULL,
    `data_hora` DATETIME NOT NULL,
    `link` VARCHAR(500) NULL,
    `sala_id` INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_encontros_projeto` (`projeto_id`),
    KEY `idx_expo_colag_encontros_data` (`data_hora`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_projeto_rubrica` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `criterio` VARCHAR(180) NOT NULL,
    `peso` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `descricao` TEXT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_rubrica_projeto` (`projeto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_projeto_materiais` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `etapa_id` INT UNSIGNED NULL,
    `titulo` VARCHAR(255) NOT NULL,
    `tipo` VARCHAR(60) NOT NULL DEFAULT 'link',
    `arquivo_url` VARCHAR(500) NULL,
    `link_externo` VARCHAR(500) NULL,
    `visibilidade` JSON NULL,
    `enviado_por` INT UNSIGNED NULL,
    `versao` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_materiais_projeto` (`projeto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_alunos_autorizacao_imagem` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `aluno_id` INT UNSIGNED NOT NULL,
    `status` ENUM('Autorizado_total','Autorizado_interno','Nao_autorizado') NOT NULL DEFAULT 'Nao_autorizado',
    `autorizado_por_responsavel_id` INT UNSIGNED NULL,
    `registrado_por` INT UNSIGNED NULL COMMENT 'admin/coordenação que registrou',
    `registrado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `revogado_em` DATETIME NULL,
    `observacao` VARCHAR(500) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_expo_colag_aut_img_aluno` (`aluno_id`),
    KEY `idx_expo_colag_aut_img_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
