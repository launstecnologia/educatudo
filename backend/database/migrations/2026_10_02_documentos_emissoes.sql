-- Arquivo versionado das emissões de documentos (declarações, autorizações e modelos).
-- Cada geração é uma linha nova. Versões antigas não são apagadas nem substituídas.
-- Tenant. Idempotente. Rollback: 2026_10_02_documentos_emissoes_rollback.sql

CREATE TABLE IF NOT EXISTS `documentos_emissoes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `modelo_id` INT(11) NOT NULL,
  `codigo` VARCHAR(80) NOT NULL,
  `nome` VARCHAR(180) NOT NULL,
  `ano_letivo` SMALLINT(5) UNSIGNED NOT NULL,
  `serie_id` INT(11) NULL,
  `serie_nome` VARCHAR(120) NULL,
  `turma_id` INT(11) NULL,
  `turma_nome` VARCHAR(180) NULL,
  `aluno_id` INT(11) NULL,
  `aluno_nome` VARCHAR(180) NULL,
  `campos_json` TEXT NULL,
  `versao` INT(11) NOT NULL DEFAULT 1,
  `total_alunos` INT(11) NOT NULL DEFAULT 0,
  `arquivo_key` VARCHAR(255) NOT NULL,
  `emitido_por` INT(11) NULL,
  `emitido_nome` VARCHAR(255) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_emissao_ano` (`ano_letivo`, `created_at`),
  KEY `idx_doc_emissao_escopo` (`modelo_id`, `ano_letivo`, `turma_id`, `aluno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
