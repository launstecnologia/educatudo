-- Expo Colag S1 — edições, projetos, etapas, visibilidade e inscrições.
-- Isolamento multi-tenant: sem coluna escola_id (banco PDO por escola).
-- Módulo exclusivo COLAG (feature key expo_colag, default off no Master).

CREATE TABLE IF NOT EXISTS `expo_colag_edicoes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome` VARCHAR(180) NOT NULL DEFAULT 'Expo Colag',
    `edicao` VARCHAR(80) NOT NULL DEFAULT '2026',
    `tema` VARCHAR(255) NULL,
    `data_evento` DATE NULL,
    `hora_inicio` TIME NULL,
    `hora_fim` TIME NULL,
    `local` VARCHAR(255) NULL,
    `mapa_url` VARCHAR(500) NULL,
    `config` JSON NULL COMMENT 'Parâmetros pedagógicos da edição (grupo, prazos, rubrica…)',
    `programacao_publica_em` DATETIME NULL,
    `voto_publico_ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `votacao_inicio` DATETIME NULL,
    `votacao_fim` DATETIME NULL,
    `checkin_ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `status` ENUM('Planejamento','Publicada','Em_andamento','Encerrada','Arquivada') NOT NULL DEFAULT 'Planejamento',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_edicoes_status` (`status`),
    KEY `idx_expo_colag_edicoes_data` (`data_evento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_projetos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `edicao_id` INT UNSIGNED NULL,
    `titulo` VARCHAR(255) NOT NULL,
    `subtitulo` VARCHAR(255) NULL,
    `area` VARCHAR(120) NULL,
    `capa_url` VARCHAR(500) NULL,
    `professor_id` INT UNSIGNED NOT NULL,
    `materia_principal_id` INT UNSIGNED NULL,
    `descricao` TEXT NULL,
    `contexto_pratico` TEXT NULL,
    `produto_esperado` TEXT NULL,
    `conexoes_interdisciplinares` TEXT NULL,
    `pre_requisitos` TEXT NULL,
    `modalidade` ENUM('Individual','Grupo','Grupo_com_papeis') NOT NULL DEFAULT 'Grupo',
    `vagas_totais` INT UNSIGNED NOT NULL DEFAULT 5,
    `vagas_minimas` INT UNSIGNED NOT NULL DEFAULT 3,
    `tamanho_grupo` INT UNSIGNED NULL,
    `modo_ingresso` ENUM('Livre','Com_aprovacao','Convite_direto') NOT NULL DEFAULT 'Livre',
    `exige_justificativa` TINYINT(1) NOT NULL DEFAULT 0,
    `lista_espera_ativa` TINYINT(1) NOT NULL DEFAULT 1,
    `publicar_em` DATETIME NULL,
    `inscricoes_inicio` DATETIME NULL,
    `inscricoes_fim` DATETIME NULL,
    `data_inicio` DATE NULL,
    `data_fim` DATE NULL,
    `data_apresentacao` DATETIME NULL,
    `briefing_entrega` TEXT NULL,
    `formatos_aceitos` JSON NULL,
    `vale_nota` TINYINT(1) NOT NULL DEFAULT 0,
    `evento_avaliativo_id` INT UNSIGNED NULL,
    `tudinha_ativa` TINYINT(1) NOT NULL DEFAULT 0,
    `tudinha_contexto` TEXT NULL,
    `custo_tudicoins` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `permite_solicitacao_recursos` TINYINT(1) NOT NULL DEFAULT 1,
    `destaque` TINYINT(1) NOT NULL DEFAULT 0,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `status` ENUM(
        'Rascunho','Publicado','Inscricoes_abertas','Em_execucao',
        'Entrega','Avaliacao','Concluido','Cancelado'
    ) NOT NULL DEFAULT 'Rascunho',
    `motivo_cancelamento` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_projetos_status_pub` (`status`, `publicar_em`),
    KEY `idx_expo_colag_projetos_professor` (`professor_id`),
    KEY `idx_expo_colag_projetos_edicao` (`edicao_id`),
    KEY `idx_expo_colag_projetos_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_projeto_materias` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `materia_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_expo_colag_proj_materia` (`projeto_id`, `materia_id`),
    KEY `idx_expo_colag_proj_materias_materia` (`materia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_projeto_professores` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `professor_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_expo_colag_proj_prof` (`projeto_id`, `professor_id`),
    KEY `idx_expo_colag_proj_prof_prof` (`professor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_projeto_objetivos` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `ordem` INT UNSIGNED NOT NULL DEFAULT 1,
    `texto` TEXT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_obj_projeto` (`projeto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_projeto_visibilidade` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `escopo` ENUM('Serie','Turma','Aluno') NOT NULL,
    `referencia_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_expo_colag_visib` (`projeto_id`, `escopo`, `referencia_id`),
    KEY `idx_expo_colag_visib_ref` (`escopo`, `referencia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_projeto_etapas` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `ordem` INT UNSIGNED NOT NULL DEFAULT 1,
    `titulo` VARCHAR(255) NOT NULL,
    `descricao` TEXT NULL,
    `data_limite` DATE NULL,
    `entregavel_esperado` TEXT NULL,
    `status` ENUM('Pendente','Em_andamento','Concluida','Atrasada') NOT NULL DEFAULT 'Pendente',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_expo_colag_etapas_projeto` (`projeto_id`, `ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `expo_colag_inscricoes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `projeto_id` INT UNSIGNED NOT NULL,
    `aluno_id` INT UNSIGNED NOT NULL,
    `papel_id` INT UNSIGNED NULL,
    `justificativa` TEXT NULL,
    `status` ENUM(
        'Aguardando','Aprovada','Recusada','Lista_espera',
        'Cancelada_aluno','Removido_professor'
    ) NOT NULL DEFAULT 'Aguardando',
    `motivo_recusa` TEXT NULL,
    `inscrito_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `decidido_em` DATETIME NULL,
    `decidido_por` INT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_expo_colag_insc_proj_aluno` (`projeto_id`, `aluno_id`),
    KEY `idx_expo_colag_insc_projeto_status` (`projeto_id`, `status`),
    KEY `idx_expo_colag_insc_aluno_status` (`aluno_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
