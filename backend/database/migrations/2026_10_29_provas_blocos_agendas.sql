-- Data e horário da avaliação por turma, dentro do mesmo subevento.
-- A data do bloco continua como padrão. Esta tabela guarda o horário de quem faz em outro momento.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_29_provas_blocos_agendas_rollback.sql

CREATE TABLE IF NOT EXISTS `provas_blocos_agendas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `bloco_id` INT NOT NULL,
  `turma_id` INT NOT NULL,
  `data_prova` DATE NOT NULL,
  `hora_inicio` TIME NULL DEFAULT NULL,
  `hora_fim` TIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bloco_turma_agenda` (`bloco_id`, `turma_id`),
  KEY `idx_provas_blocos_agendas_turma` (`turma_id`),
  CONSTRAINT `fk_provas_blocos_agendas_bloco`
    FOREIGN KEY (`bloco_id`) REFERENCES `provas_blocos` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_provas_blocos_agendas_turma`
    FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
