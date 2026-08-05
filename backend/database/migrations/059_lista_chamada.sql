-- Lista de chamada por turma: sexo do aluno, configuração e numeração

ALTER TABLE `alunos`
  ADD COLUMN `sexo` ENUM('M','F','N') NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `turmas_lista_config` (
  `turma_id` int(11) NOT NULL,
  `ano_letivo_id` int(11) NOT NULL,
  `criterio_ordem` enum('alfabetica','meninas_primeiro','meninos_primeiro') NOT NULL DEFAULT 'alfabetica',
  `data_corte` date DEFAULT NULL,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`turma_id`, `ano_letivo_id`),
  KEY `idx_turmas_lista_ano` (`ano_letivo_id`),
  CONSTRAINT `fk_turmas_lista_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_turmas_lista_ano` FOREIGN KEY (`ano_letivo_id`) REFERENCES `ano_letivo` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `alunos_turma_chamada` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aluno_id` int(11) NOT NULL,
  `turma_id` int(11) NOT NULL,
  `ano_letivo_id` int(11) NOT NULL,
  `numero_chamada` smallint(5) unsigned NOT NULL,
  `entrada_tardia` tinyint(1) NOT NULL DEFAULT 0,
  `marcado_tr` tinyint(1) NOT NULL DEFAULT 0,
  `data_entrada_turma` date NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aluno_turma_ano` (`aluno_id`, `turma_id`, `ano_letivo_id`),
  UNIQUE KEY `uq_turma_numero` (`turma_id`, `ano_letivo_id`, `numero_chamada`),
  KEY `idx_chamada_turma` (`turma_id`, `ano_letivo_id`),
  CONSTRAINT `fk_chamada_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_chamada_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_chamada_ano` FOREIGN KEY (`ano_letivo_id`) REFERENCES `ano_letivo` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: numeração alfabética por turma (alunos ativos com turma)
INSERT IGNORE INTO `alunos_turma_chamada` (`aluno_id`, `turma_id`, `ano_letivo_id`, `numero_chamada`, `entrada_tardia`, `data_entrada_turma`)
SELECT
  ranked.aluno_id,
  ranked.turma_id,
  ranked.ano_letivo_id,
  ranked.rn,
  0,
  CURDATE()
FROM (
  SELECT
    a.id AS aluno_id,
    a.turma_id,
    COALESCE(
      (SELECT al.id FROM ano_letivo al WHERE al.ano = t.ano_letivo ORDER BY al.id DESC LIMIT 1),
      (SELECT al2.id FROM ano_letivo al2 ORDER BY al2.ano DESC LIMIT 1)
    ) AS ano_letivo_id,
    ROW_NUMBER() OVER (PARTITION BY a.turma_id ORDER BY a.nome ASC) AS rn
  FROM alunos a
  INNER JOIN turmas t ON t.id = a.turma_id
  WHERE a.turma_id IS NOT NULL
    AND a.ativo = 1
) ranked
WHERE ranked.ano_letivo_id IS NOT NULL;
