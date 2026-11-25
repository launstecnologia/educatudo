-- Homologação do demonstrativo fixo do Colag.
-- Guarda o recorte filtrado para avisar se uma nota mudar depois.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_11_25_boletim_colag_homologacao_rollback.sql

CREATE TABLE IF NOT EXISTS `boletim_colag_homologacoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ano` SMALLINT NOT NULL,
  `serie` TINYINT NOT NULL DEFAULT 0,
  `turma_id` INT NOT NULL DEFAULT 0,
  `aluno_id` INT NOT NULL DEFAULT 0,
  `bimestre` TINYINT NOT NULL DEFAULT 0,
  `nota_campo` VARCHAR(20) NOT NULL DEFAULT 'resultado',
  `nota_minima` DECIMAL(4,1) NULL DEFAULT NULL,
  `resumo_json` MEDIUMTEXT NOT NULL,
  `usuario_id` INT NOT NULL DEFAULT 0,
  `usuario_nome` VARCHAR(160) NOT NULL DEFAULT '',
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_boletim_colag_homolog_filtro` (`ano`, `serie`, `turma_id`, `aluno_id`, `bimestre`, `nota_campo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
