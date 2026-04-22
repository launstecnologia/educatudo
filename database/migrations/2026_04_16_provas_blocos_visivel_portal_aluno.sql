-- Portal do aluno: exibir ou não o evento de prova (ex.: provas bimestrais só internas).
-- Execute no banco da escola (MySQL/MariaDB).

ALTER TABLE provas_blocos
  ADD COLUMN visivel_no_portal_aluno TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1=aluno vê no portal; 0=só coordenação/professor'
  AFTER bimestre;

-- Eventos com bimestre (prova bimestral): por padrão não aparecem para o aluno até a coordenação ativar.
UPDATE provas_blocos
SET visivel_no_portal_aluno = 0
WHERE deleted_at IS NULL
  AND bimestre IS NOT NULL
  AND bimestre BETWEEN 1 AND 4;
