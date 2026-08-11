-- Expo Colag S4 — tarefas/atribuições, stands+QR, programação.
-- Sem escola_id (isolamento por PDO).

SET @has_origem := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'expo_colag_projeto_materiais'
      AND COLUMN_NAME = 'origem'
);
SET @sql_origem := IF(
    @has_origem > 0,
    'SELECT 1',
    'ALTER TABLE `expo_colag_projeto_materiais`
        ADD COLUMN `origem` ENUM(\'Wizard\',\'Execucao\') NOT NULL DEFAULT \'Wizard\'
            COMMENT \'Wizard = sincronizado no formulário; Execucao = adicionado no painel\'
            AFTER `versao`'
);
PREPARE stmt_origem FROM @sql_origem;
EXECUTE stmt_origem;
DEALLOCATE PREPARE stmt_origem;

CREATE TABLE IF NOT EXISTS `expo_colag_projeto_tarefas` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `etapa_id` INT UNSIGNED NULL,
    `titulo` VARCHAR(255) NOT NULL,
    `descricao` TEXT NULL,
    `tipo_entregavel` ENUM('Nenhum','Arquivo','Texto','Link') NOT NULL DEFAULT 'Nenhum',
    `data_limite` DATETIME NULL,
    `obrigatoria` TINYINT(1) NOT NULL DEFAULT 1,
    `criada_por` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_tarefas_projeto` (`projeto_id`),
    KEY `idx_expo_colag_tarefas_etapa` (`etapa_id`),
    KEY `idx_expo_colag_tarefas_limite` (`data_limite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_projeto_tarefa_atribuicoes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tarefa_id` INT UNSIGNED NOT NULL,
    `inscricao_id` INT UNSIGNED NOT NULL,
    `status` ENUM(
        'Pendente','Em_andamento','Entregue','Concluida','Atrasada','Devolvida'
    ) NOT NULL DEFAULT 'Pendente',
    `entrega_conteudo` TEXT NULL,
    `entrega_arquivo_url` VARCHAR(500) NULL,
    `entregue_em` DATETIME NULL,
    `avaliado_em` DATETIME NULL,
    `comentario_professor` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_expo_colag_tarefa_insc` (`tarefa_id`, `inscricao_id`),
    KEY `idx_expo_colag_atr_insc_status` (`inscricao_id`, `status`, `tarefa_id`),
    KEY `idx_expo_colag_atr_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_setores` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `edicao_id` INT UNSIGNED NOT NULL,
    `nome` VARCHAR(120) NOT NULL,
    `cor` VARCHAR(20) NULL,
    `ordem` INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_setores_edicao` (`edicao_id`, `ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_stands` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `edicao_id` INT UNSIGNED NOT NULL,
    `projeto_id` INT UNSIGNED NOT NULL,
    `setor_id` INT UNSIGNED NULL,
    `numero` VARCHAR(10) NULL,
    `posicao_mapa` JSON NULL,
    `qr_token` CHAR(32) NOT NULL,
    `horario_apresentacao` DATETIME NULL,
    `resumo_publico` TEXT NULL,
    `capa_url` VARCHAR(500) NULL,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_expo_colag_stand_projeto` (`projeto_id`),
    UNIQUE KEY `uk_expo_colag_stand_qr` (`qr_token`),
    KEY `idx_expo_colag_stand_edicao` (`edicao_id`),
    KEY `idx_expo_colag_stand_setor` (`setor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_programacao` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `edicao_id` INT UNSIGNED NOT NULL,
    `titulo` VARCHAR(255) NOT NULL,
    `descricao` TEXT NULL,
    `tipo` VARCHAR(60) NOT NULL DEFAULT 'Geral',
    `hora_inicio` DATETIME NOT NULL,
    `hora_fim` DATETIME NULL,
    `local` VARCHAR(255) NULL,
    `setor_id` INT UNSIGNED NULL,
    `ordem` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_prog_edicao` (`edicao_id`, `hora_inicio`),
    KEY `idx_expo_colag_prog_setor` (`setor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
