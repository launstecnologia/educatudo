-- M5: Tabela matricula (aluno ↔ turma por ano; suporta mais de um curso)
-- Backfill B5 popula a partir de alunos.turma_id.

CREATE TABLE IF NOT EXISTS `matricula` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aluno_id` int(11) NOT NULL,
  `turma_id` int(11) NOT NULL,
  `ano_letivo_id` int(11) NOT NULL,
  `data_entrada` date NOT NULL,
  `data_saida` date DEFAULT NULL,
  `status` enum('ativa','transferido','concluido') NOT NULL DEFAULT 'ativa',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_matricula_aluno_turma_ano` (`aluno_id`, `turma_id`, `ano_letivo_id`),
  KEY `idx_matricula_aluno` (`aluno_id`),
  KEY `idx_matricula_turma` (`turma_id`),
  KEY `idx_matricula_ano_letivo` (`ano_letivo_id`),
  KEY `idx_matricula_status` (`status`),
  CONSTRAINT `fk_matricula_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_matricula_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_matricula_ano_letivo` FOREIGN KEY (`ano_letivo_id`) REFERENCES `ano_letivo` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
