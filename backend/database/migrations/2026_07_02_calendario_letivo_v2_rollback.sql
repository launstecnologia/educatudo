-- Rollback: remove campos adicionados na v2
ALTER TABLE `calendario_letivo_eventos`
  DROP COLUMN IF EXISTS `visivel_pais`,
  DROP COLUMN IF EXISTS `visivel_professor`,
  DROP COLUMN IF EXISTS `visivel_aluno`,
  DROP COLUMN IF EXISTS `local_evento`,
  DROP COLUMN IF EXISTS `link_reuniao`;

ALTER TABLE `calendario_letivo_eventos`
  MODIFY COLUMN `tipo` ENUM('feriado','recesso','reposicao','evento','suspensao') NOT NULL DEFAULT 'feriado';
