-- M3: Tabela serie (estrutura escolar normalizada, depende de curso)
-- curso_id referencia a tabela curso (022/023).

CREATE TABLE IF NOT EXISTS `serie` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `curso_id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_serie_curso_nome` (`curso_id`, `nome`),
  KEY `idx_serie_curso` (`curso_id`),
  KEY `idx_serie_ativo` (`ativo`),
  CONSTRAINT `fk_serie_curso` FOREIGN KEY (`curso_id`) REFERENCES `curso` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
