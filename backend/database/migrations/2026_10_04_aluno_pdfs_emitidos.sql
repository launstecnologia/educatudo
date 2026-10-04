-- Arquivo de cada PDF gerado para o aluno (boletim, notas, declarações, histórico e demais).
-- O binário fica no storage da escola (S3 quando configurado). A linha só aponta a chave.
-- Tenant. Idempotente. Rollback: 2026_10_04_aluno_pdfs_emitidos_rollback.sql

CREATE TABLE IF NOT EXISTS `aluno_pdfs_emitidos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `aluno_id` INT(11) NOT NULL,
  `tipo` VARCHAR(40) NOT NULL,
  `titulo` VARCHAR(180) NOT NULL,
  `arquivo_key` VARCHAR(255) NOT NULL,
  `arquivo_nome` VARCHAR(255) NOT NULL,
  `emitido_por` INT(11) NULL,
  `emitido_nome` VARCHAR(180) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aluno_pdf_emitido` (`aluno_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
