-- Alunos convocados para um evento de recuperação.
-- Sem linhas no evento: vale a turma inteira. Com linhas: só esses alunos.
-- Tenant. Sem escola_id: o isolamento é o banco. Idempotente.
-- Rollback: 2026_10_29_eventos_avaliativos_alunos_rollback.sql

CREATE TABLE IF NOT EXISTS `eventos_avaliativos_alunos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `evento_id` INT UNSIGNED NOT NULL,
  `aluno_id` INT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_evento_aluno` (`evento_id`, `aluno_id`),
  KEY `idx_eventos_avaliativos_alunos_aluno` (`aluno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
