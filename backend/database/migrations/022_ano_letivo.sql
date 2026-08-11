-- M1: Tabela ano_letivo (estrutura escolar normalizada)
-- Execute após backup. Colunas antigas em turmas não são removidas.

CREATE TABLE IF NOT EXISTS `ano_letivo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ano` int(11) NOT NULL COMMENT 'Ano civil do ano letivo (ex: 2025)',
  `data_inicio` date DEFAULT NULL COMMENT 'Início do ano letivo',
  `data_fim` date DEFAULT NULL COMMENT 'Fim do ano letivo',
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ano_letivo_ano` (`ano`),
  KEY `idx_ano_letivo_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
