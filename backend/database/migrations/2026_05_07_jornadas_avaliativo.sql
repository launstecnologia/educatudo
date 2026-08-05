-- Campo avaliativo da jornada (sim/nao) para admin/professor.
-- Execute no MySQL do tenant. Se der coluna duplicada, ela ja existe.

ALTER TABLE jornadas
  ADD COLUMN avaliativo TINYINT(1) NOT NULL DEFAULT 0
  COMMENT '0 = nao, 1 = sim'
  AFTER bimestre;
