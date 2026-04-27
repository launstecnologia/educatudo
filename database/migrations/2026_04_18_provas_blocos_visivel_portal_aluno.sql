-- Mesmo conteúdo que src/database/migrations/2026_04_18_provas_blocos_visivel_portal_aluno.sql
-- (cópia na raiz do monorepo para quem só versiona a pasta database/ do deploy legado).

ALTER TABLE provas_blocos
  ADD COLUMN visivel_no_portal_aluno TINYINT(1) NOT NULL DEFAULT 1
    COMMENT '1=aluno vê no portal; 0=só coordenação/professor'
  AFTER bimestre;

UPDATE provas_blocos
SET visivel_no_portal_aluno = 0
WHERE deleted_at IS NULL
  AND bimestre IS NOT NULL
  AND bimestre BETWEEN 1 AND 4;
