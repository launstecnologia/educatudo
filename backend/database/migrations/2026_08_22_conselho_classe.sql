-- Conselho de Classe: sessão colegiada por turma/ano/bimestre.
-- Consome boletim, diário, frequência e ocorrências — não duplica nota nem falta.
-- Tenant. Idempotente. Rollback: 2026_08_22_conselho_classe_rollback.sql

SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS `conselho_sessoes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `turma_id` INT NOT NULL,
  `ano_letivo` SMALLINT UNSIGNED NOT NULL,
  `bimestre` TINYINT UNSIGNED NOT NULL COMMENT 'Bimestre 1 a 4',
  `status` ENUM('em_preparacao','em_andamento','finalizado','reaberto') NOT NULL DEFAULT 'em_preparacao',
  `data_reuniao` DATE NULL DEFAULT NULL,
  `pauta` TEXT NULL,
  `criado_por` INT NULL DEFAULT NULL,
  `aberto_por` INT NULL DEFAULT NULL,
  `aberto_em` DATETIME NULL DEFAULT NULL,
  `finalizado_por` INT NULL DEFAULT NULL,
  `finalizado_em` DATETIME NULL DEFAULT NULL,
  `reaberto_por` INT NULL DEFAULT NULL,
  `reaberto_em` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conselho_sessao_turma_periodo` (`turma_id`, `ano_letivo`, `bimestre`),
  KEY `idx_conselho_sessoes_status` (`status`),
  KEY `idx_conselho_sessoes_ano` (`ano_letivo`, `bimestre`),
  CONSTRAINT `fk_conselho_sessoes_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conselho_participantes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `sessao_id` INT NOT NULL,
  `professor_id` INT NULL DEFAULT NULL,
  `usuario_id` INT NULL DEFAULT NULL,
  `nome` VARCHAR(150) NOT NULL,
  `cargo` VARCHAR(40) NOT NULL DEFAULT 'professor',
  `presente` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conselho_participantes_sessao` (`sessao_id`),
  KEY `idx_conselho_participantes_professor` (`professor_id`),
  CONSTRAINT `fk_conselho_participantes_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `conselho_sessoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Histórico append-only: nunca atualiza a linha, sempre insere. A vigente é a mais recente.
CREATE TABLE IF NOT EXISTS `conselho_deliberacoes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `sessao_id` INT NOT NULL,
  `aluno_id` INT NOT NULL,
  `materia_id` INT NULL DEFAULT NULL COMMENT 'NULL = decisão da situação geral do aluno',
  `resultado_anterior` VARCHAR(80) NOT NULL,
  `resultado_decisao` VARCHAR(80) NOT NULL,
  `justificativa` TEXT NOT NULL,
  `registrado_por` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conselho_deliberacoes_sessao_aluno` (`sessao_id`, `aluno_id`),
  KEY `idx_conselho_deliberacoes_aluno` (`aluno_id`),
  CONSTRAINT `fk_conselho_deliberacoes_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `conselho_sessoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conselho_deliberacoes_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conselho_encaminhamentos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `sessao_id` INT NOT NULL,
  `aluno_id` INT NOT NULL,
  `tipo` ENUM('recuperacao','acompanhamento_pedagogico','contato_responsavel','atendimento','encaminhamento','observacao','decisao_final') NOT NULL,
  `detalhe` TEXT NOT NULL,
  `ocorrencia_id` INT NULL DEFAULT NULL,
  `criado_por` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conselho_encaminhamentos_sessao_aluno` (`sessao_id`, `aluno_id`),
  KEY `idx_conselho_encaminhamentos_ocorrencia` (`ocorrencia_id`),
  CONSTRAINT `fk_conselho_encaminhamentos_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `conselho_sessoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conselho_encaminhamentos_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conselho_atas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `sessao_id` INT NOT NULL,
  `pauta` TEXT NULL,
  `sintese` TEXT NULL,
  `decisoes` TEXT NULL,
  `conteudo_json` MEDIUMTEXT NULL COMMENT 'Snapshot da matriz e deliberações no momento da geração',
  `gerada_por` INT NULL DEFAULT NULL,
  `gerada_em` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conselho_atas_sessao` (`sessao_id`),
  CONSTRAINT `fk_conselho_atas_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `conselho_sessoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conselho_observacoes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `sessao_id` INT NOT NULL,
  `aluno_id` INT NOT NULL,
  `professor_id` INT NOT NULL,
  `texto` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conselho_observacoes` (`sessao_id`, `aluno_id`, `professor_id`),
  CONSTRAINT `fk_conselho_observacoes_sessao` FOREIGN KEY (`sessao_id`) REFERENCES `conselho_sessoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_conselho_observacoes_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
