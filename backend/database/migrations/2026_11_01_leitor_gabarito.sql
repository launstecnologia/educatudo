-- Leitor de gabarito: folha impressa pelo sistema e o scan lido dela.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_11_01_leitor_gabarito_rollback.sql

CREATE TABLE IF NOT EXISTS `leitor_gabarito_folhas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prova_id` int NOT NULL,
  `aluno_id` int NOT NULL,
  `questoes_json` longtext NOT NULL COMMENT 'Versão do desenho e alternativas impressas',
  `caminho_png` varchar(500) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_leitor_folha_prova_aluno` (`prova_id`, `aluno_id`),
  KEY `idx_leitor_folha_prova` (`prova_id`),
  CONSTRAINT `fk_leitor_folha_prova` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_leitor_folha_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leitor_gabarito_leituras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prova_id` int NOT NULL,
  `folha_id` int DEFAULT NULL,
  `aluno_id` int DEFAULT NULL,
  `caminho_scan` varchar(500) NOT NULL,
  `status` enum('na_fila','lendo','revisao','confirmada','erro') NOT NULL DEFAULT 'na_fila',
  `mensagem` varchar(500) DEFAULT NULL,
  `marcacoes_json` longtext DEFAULT NULL,
  `nota` decimal(10,2) DEFAULT NULL,
  `criado_por` int DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lido_em` datetime DEFAULT NULL,
  `confirmado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_leitor_leitura_prova` (`prova_id`),
  KEY `idx_leitor_leitura_status` (`status`),
  KEY `idx_leitor_leitura_folha` (`folha_id`),
  CONSTRAINT `fk_leitor_leitura_prova` FOREIGN KEY (`prova_id`) REFERENCES `provas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_leitor_leitura_folha` FOREIGN KEY (`folha_id`) REFERENCES `leitor_gabarito_folhas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leitor_leitura_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
