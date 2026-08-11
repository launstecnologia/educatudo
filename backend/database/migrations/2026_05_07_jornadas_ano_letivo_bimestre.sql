-- Ano letivo e bimestre para jornadas (admin/professor).
-- Execute no MySQL do tenant. Se aparecer erro de coluna duplicada, a coluna já existe.

ALTER TABLE jornadas
  ADD COLUMN ano_letivo SMALLINT UNSIGNED NULL DEFAULT NULL
  COMMENT 'Ano letivo da jornada'
  AFTER materia_id;

ALTER TABLE jornadas
  ADD COLUMN bimestre TINYINT UNSIGNED NULL DEFAULT NULL
  COMMENT 'Bimestre 1 a 4 da jornada'
  AFTER ano_letivo;
