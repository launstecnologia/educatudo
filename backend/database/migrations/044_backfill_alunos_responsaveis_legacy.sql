-- Backfill legado: alunos.responsavel_id -> alunos_responsaveis

INSERT INTO alunos_responsaveis (aluno_id, responsavel_id, tipo_vinculo, is_financeiro, ativo, created_at, updated_at)
SELECT
    a.id AS aluno_id,
    a.responsavel_id AS responsavel_id,
    'responsavel' AS tipo_vinculo,
    0 AS is_financeiro,
    1 AS ativo,
    NOW() AS created_at,
    NOW() AS updated_at
FROM alunos a
WHERE a.responsavel_id IS NOT NULL
  AND EXISTS (SELECT 1 FROM responsaveis r WHERE r.id = a.responsavel_id)
  AND NOT EXISTS (
      SELECT 1
      FROM alunos_responsaveis ar
      WHERE ar.aluno_id = a.id
        AND ar.responsavel_id = a.responsavel_id
  );
