-- Ano letivo e bimestre do evento de prova (coordenação).
-- Execute no MySQL do tenant. Se aparecer erro de coluna duplicada, a coluna já existe — pode ignorar esse ALTER.

ALTER TABLE provas_blocos
  ADD COLUMN ano_letivo SMALLINT UNSIGNED NULL DEFAULT NULL
  COMMENT 'Ano letivo do evento'
  AFTER descricao;

ALTER TABLE provas_blocos
  ADD COLUMN bimestre TINYINT UNSIGNED NULL DEFAULT NULL
  COMMENT 'Bimestre 1 a 4'
  AFTER ano_letivo;
