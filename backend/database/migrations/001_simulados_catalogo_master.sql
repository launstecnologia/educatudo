-- Catálogo central de simulados no banco Master (simulados_*)
-- Executado via MasterMigrationsController / MysqlProvisioningService::runMasterMigrations

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Bancas (ENEM, Fuvest, Unicamp, etc.)
CREATE TABLE IF NOT EXISTS `simulados_bancas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `descricao` text,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `simulados_bancas_slug` (`slug`),
  KEY `simulados_bancas_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Provas (por banca)
CREATE TABLE IF NOT EXISTS `simulados_provas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `banca_id` int unsigned NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `ano` int unsigned NOT NULL,
  `fase` varchar(80) DEFAULT NULL,
  `tipo` varchar(80) DEFAULT NULL,
  `descricao` text,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `simulados_provas_banca_id` (`banca_id`),
  KEY `simulados_provas_ano` (`ano`),
  KEY `simulados_provas_ativo` (`ativo`),
  CONSTRAINT `fk_simulados_provas_banca` FOREIGN KEY (`banca_id`) REFERENCES `simulados_bancas` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categorias pedagógicas (área, disciplina, tema, subtema)
CREATE TABLE IF NOT EXISTS `simulados_categorias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned DEFAULT NULL,
  `tipo` enum('area','disciplina','tema','subtema') NOT NULL,
  `nome` varchar(120) NOT NULL,
  `slug` varchar(80) NOT NULL,
  `descricao` text,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ordem` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `simulados_categorias_parent_id` (`parent_id`),
  KEY `simulados_categorias_tipo` (`tipo`),
  KEY `simulados_categorias_ativo` (`ativo`),
  CONSTRAINT `fk_simulados_categorias_parent` FOREIGN KEY (`parent_id`) REFERENCES `simulados_categorias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Questões
CREATE TABLE IF NOT EXISTS `simulados_questoes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `prova_id` int unsigned NOT NULL,
  `indice` int unsigned NOT NULL DEFAULT 0,
  `titulo` varchar(255) DEFAULT NULL,
  `contexto` longtext,
  `enunciado` longtext,
  `tipo_questao` enum('objetiva','discursiva') NOT NULL DEFAULT 'objetiva',
  `idioma` varchar(20) DEFAULT 'pt',
  `gabarito` varchar(20) DEFAULT NULL,
  `comentario_resolucao` longtext,
  `dificuldade` varchar(20) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'published',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `simulados_questoes_prova_id` (`prova_id`),
  KEY `simulados_questoes_tipo` (`tipo_questao`),
  KEY `simulados_questoes_status` (`status`),
  CONSTRAINT `fk_simulados_questoes_prova` FOREIGN KEY (`prova_id`) REFERENCES `simulados_provas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alternativas (questões objetivas)
CREATE TABLE IF NOT EXISTS `simulados_alternativas` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `questao_id` int unsigned NOT NULL,
  `letra` varchar(5) NOT NULL,
  `texto` text,
  `arquivo` varchar(500) DEFAULT NULL,
  `is_correta` tinyint(1) NOT NULL DEFAULT 0,
  `ordem` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `simulados_alternativas_questao_id` (`questao_id`),
  CONSTRAINT `fk_simulados_alternativas_questao` FOREIGN KEY (`questao_id`) REFERENCES `simulados_questoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Arquivos/imagens por questão
CREATE TABLE IF NOT EXISTS `simulados_questoes_arquivos` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `questao_id` int unsigned NOT NULL,
  `arquivo_url` varchar(500) NOT NULL,
  `tipo_arquivo` varchar(40) DEFAULT 'imagem',
  `ordem` int unsigned NOT NULL DEFAULT 0,
  `legenda` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `simulados_questoes_arquivos_questao_id` (`questao_id`),
  CONSTRAINT `fk_simulados_questoes_arquivos_questao` FOREIGN KEY (`questao_id`) REFERENCES `simulados_questoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Vínculo questão <-> categorias (N:N)
CREATE TABLE IF NOT EXISTS `simulados_questoes_categorias` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `questao_id` int unsigned NOT NULL,
  `categoria_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `simulados_questoes_categorias_uniq` (`questao_id`,`categoria_id`),
  KEY `simulados_questoes_categorias_categoria_id` (`categoria_id`),
  CONSTRAINT `fk_simulados_qc_questao` FOREIGN KEY (`questao_id`) REFERENCES `simulados_questoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_simulados_qc_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `simulados_categorias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- Carga inicial: banca ENEM
INSERT IGNORE INTO `simulados_bancas` (`id`, `nome`, `slug`, `descricao`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 'ENEM', 'enem', 'Exame Nacional do Ensino Médio', 1, NOW(), NOW());
