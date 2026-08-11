-- Backfill B1–B5: popular novas tabelas a partir dos dados existentes.
-- Executar APÓS 022 a 026 aplicadas. Não remove colunas/tabelas antigas.
--
-- Validação sugerida após executar:
--   SELECT COUNT(*) FROM alunos WHERE turma_id IS NOT NULL;
--   SELECT COUNT(*) FROM matricula WHERE status = 'ativa';
--   (devem coincidir)
--   SELECT COUNT(*) FROM turmas WHERE ano_letivo_id IS NULL OR serie_id IS NULL;
--   (deve ser 0 após B4, ou aceitar NULL se não houver curso/série mapeado)

-- ---------------------------------------------------------------------------
-- B1: Popular ano_letivo a partir de turmas.ano_letivo (INT)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `ano_letivo` (`ano`, `data_inicio`, `data_fim`, `ativo`)
SELECT DISTINCT
  t.ano_letivo,
  CAST(CONCAT(t.ano_letivo, '-02-01') AS DATE),
  CAST(CONCAT(t.ano_letivo, '-12-15') AS DATE),
  1
FROM turmas t
WHERE t.ano_letivo IS NOT NULL;

-- ---------------------------------------------------------------------------
-- B2: Popular curso a partir de tipos_curso (se existir)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `curso` (`nome`, `descricao`, `ativo`, `ordem`)
SELECT nome, NULL, ativo, ordem
FROM tipos_curso
WHERE NOT EXISTS (SELECT 1 FROM curso c WHERE c.nome = tipos_curso.nome);

-- Se tipos_curso estiver vazio: criar cursos a partir de turmas.tipo_ensino
INSERT IGNORE INTO `curso` (`nome`, `descricao`, `ativo`, `ordem`)
SELECT DISTINCT t.tipo_ensino, NULL, 1, 0
FROM turmas t
WHERE t.tipo_ensino IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM curso c WHERE c.nome = t.tipo_ensino);

-- ---------------------------------------------------------------------------
-- B3: Popular serie a partir de cursos + tipos_curso (curso novo por nome)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `serie` (`curso_id`, `nome`, `ordem`, `ativo`)
SELECT c.id, cur.nome, cur.ordem, cur.ativo
FROM cursos cur
JOIN tipos_curso tc ON cur.tipo_curso_id = tc.id
JOIN curso c ON c.nome = tc.nome
WHERE NOT EXISTS (
  SELECT 1 FROM serie s WHERE s.curso_id = c.id AND s.nome = cur.nome
);

-- Se não houver cursos/tipos_curso: criar séries por turmas.serie e um curso genérico
INSERT IGNORE INTO `curso` (`nome`, `descricao`, `ativo`, `ordem`)
SELECT 'Geral', NULL, 1, 0
FROM (SELECT 1) x
WHERE NOT EXISTS (SELECT 1 FROM curso LIMIT 1);

INSERT IGNORE INTO `serie` (`curso_id`, `nome`, `ordem`, `ativo`)
SELECT (SELECT id FROM curso WHERE nome = 'Geral' LIMIT 1), t.serie, 0, 1
FROM turmas t
WHERE t.serie IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM serie s
    WHERE s.curso_id = (SELECT id FROM curso WHERE nome = 'Geral' LIMIT 1)
      AND s.nome = t.serie
  )
GROUP BY t.serie;

-- ---------------------------------------------------------------------------
-- B4: Atualizar turmas com ano_letivo_id e serie_id
-- ---------------------------------------------------------------------------
UPDATE turmas t
SET t.ano_letivo_id = (SELECT id FROM ano_letivo al WHERE al.ano = t.ano_letivo LIMIT 1)
WHERE t.ano_letivo IS NOT NULL AND (t.ano_letivo_id IS NULL);

-- serie_id: por curso_id (cursos) -> tipos_curso -> curso -> serie
UPDATE turmas t
JOIN cursos cur ON cur.id = t.curso_id
JOIN tipos_curso tc ON tc.id = cur.tipo_curso_id
JOIN curso c ON c.nome = tc.nome
JOIN serie s ON s.curso_id = c.id AND s.nome = cur.nome
SET t.serie_id = s.id
WHERE t.serie_id IS NULL;

-- serie_id: turmas sem curso_id — match por nome da série (se único)
UPDATE turmas t
SET t.serie_id = (
  SELECT s.id FROM serie s WHERE s.nome = t.serie LIMIT 1
)
WHERE t.serie_id IS NULL
  AND t.serie IS NOT NULL
  AND (SELECT COUNT(*) FROM serie s2 WHERE s2.nome = t.serie) = 1;

-- ---------------------------------------------------------------------------
-- B5: Popular matricula a partir de alunos.turma_id
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `matricula` (`aluno_id`, `turma_id`, `ano_letivo_id`, `data_entrada`, `data_saida`, `status`)
SELECT
  a.id,
  a.turma_id,
  t.ano_letivo_id,
  COALESCE(DATE(a.created_at), CURDATE()),
  NULL,
  'ativa'
FROM alunos a
JOIN turmas t ON t.id = a.turma_id
WHERE a.turma_id IS NOT NULL
  AND t.ano_letivo_id IS NOT NULL;
