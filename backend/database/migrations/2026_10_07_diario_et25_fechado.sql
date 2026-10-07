-- Fecha o diário da população ET25 nos 3 trimestres de 2025.
-- Um registro por turma + componente + professor, no mesmo número de período
-- do fechamento e do Conselho. Idempotente: não reabre nem duplica.
-- Tenant. Rollback: 2026_10_07_diario_et25_fechado_rollback.sql
-- Em escola sem o seed ET25, o INSERT não gera linha.
-- Rode também 2026_10_07_conselho_et25_finalizado.sql se o Conselho ainda
-- estiver sem sessão.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @admin := COALESCE(
  (SELECT id FROM usuarios WHERE email = 'admin@educateste.local' LIMIT 1),
  (SELECT id FROM usuarios WHERE tipo = 'admin_escola' ORDER BY id LIMIT 1)
);

INSERT INTO diario_fechamentos (
  turma_id, materia_id, professor_id, ano_letivo, bimestre, status, fechado_por, fechado_em, observacoes
)
SELECT g.turma_id, g.materia_id, g.professor_id, 2025, per.bim, 'fechado', @admin,
       TIMESTAMP('2025-12-12', '18:00:00'), 'ET25 diário fechado com o trimestre'
FROM (
  SELECT DISTINCT gh.turma_id, gh.materia_id, gh.professor_id
  FROM grade_horaria gh
  INNER JOIN turmas t ON t.id = gh.turma_id AND t.observacoes LIKE 'ET25 %'
) g
CROSS JOIN (
  SELECT 1 AS bim UNION ALL SELECT 2 UNION ALL SELECT 3
) per
WHERE NOT EXISTS (
  SELECT 1 FROM diario_fechamentos df
  WHERE df.turma_id = g.turma_id
    AND df.materia_id = g.materia_id
    AND df.professor_id = g.professor_id
    AND df.ano_letivo = 2025
    AND df.bimestre = per.bim
);
