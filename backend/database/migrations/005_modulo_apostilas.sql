-- Migration incremental: módulo Apostilas com tabelas dedicadas
-- Garante que a tabela turmas exista (bancos criados sem dump completo, ex.: Colégio Educa)
CREATE TABLE IF NOT EXISTS `turmas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL COMMENT 'Ex: 1ºA, 2ºB',
  `ano_letivo` int(11) NOT NULL,
  `serie` varchar(50) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `tipo_ensino` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ano_letivo` (`ano_letivo`),
  KEY `idx_serie` (`serie`),
  KEY `idx_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modulos_apostilas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `turma_id` int(11) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descricao` text DEFAULT NULL,
  `visibilidade` enum('aluno','professor','ambos') NOT NULL DEFAULT 'ambos',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_modulos_apostilas_turma` (`turma_id`),
  KEY `idx_modulos_apostilas_visibilidade` (`visibilidade`),
  KEY `idx_modulos_apostilas_created_at` (`created_at`),
  CONSTRAINT `fk_modulos_apostilas_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modulos_apostilas_anexos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modulo_apostila_id` int(11) NOT NULL,
  `caminho` varchar(255) NOT NULL,
  `nome_original` varchar(255) NOT NULL,
  `extensao` varchar(10) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `tamanho` bigint(20) DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_modulos_apostilas_anexos_modulo` (`modulo_apostila_id`),
  CONSTRAINT `fk_modulos_apostilas_anexos_modulo` FOREIGN KEY (`modulo_apostila_id`) REFERENCES `modulos_apostilas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `modulos_apostilas_turmas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `modulo_apostila_id` int(11) NOT NULL,
  `turma_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_modulo_apostila_turma` (`modulo_apostila_id`,`turma_id`),
  KEY `idx_modulos_apostilas_turmas_turma` (`turma_id`),
  CONSTRAINT `fk_modulos_apostilas_turmas_modulo` FOREIGN KEY (`modulo_apostila_id`) REFERENCES `modulos_apostilas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_modulos_apostilas_turmas_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
