-- Calendário Letivo v2: avaliacao, link_reuniao, local_evento, visibilidade por perfil
-- Executar em cada banco de tenant.

ALTER TABLE `calendario_letivo_eventos`
  MODIFY COLUMN `tipo` ENUM('feriado','recesso','reposicao','evento','suspensao','avaliacao') NOT NULL DEFAULT 'feriado';

ALTER TABLE `calendario_letivo_eventos`
  ADD COLUMN `link_reuniao`      VARCHAR(500) NULL AFTER `descricao`,
  ADD COLUMN `local_evento`      VARCHAR(255) NULL AFTER `link_reuniao`,
  ADD COLUMN `visivel_aluno`     TINYINT(1) NOT NULL DEFAULT 0 AFTER `local_evento`,
  ADD COLUMN `visivel_professor` TINYINT(1) NOT NULL DEFAULT 0 AFTER `visivel_aluno`,
  ADD COLUMN `visivel_pais`      TINYINT(1) NOT NULL DEFAULT 0 AFTER `visivel_professor`;
