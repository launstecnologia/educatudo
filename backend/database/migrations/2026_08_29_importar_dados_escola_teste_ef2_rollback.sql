-- Rollback do seed Escola Educa Teste (Fundamental II 2026).
-- Remove só o que este seed criou (prefixos etf.%, ETF-%, etf-2026-%, ETF Faltas).
-- Não apaga tipos de avaliação, catálogo de componentes, unidade, ano, calendário
-- nem equipe — esses são compartilhados com o seed do Ensino Médio.
-- Justificativa dos DELETE: dados sintéticos deste arquivo, isolados por prefixo/código.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '-03:00';

DROP PROCEDURE IF EXISTS seed_escola_teste_ef2;

-- ── Notas / eventos / boletim ────────────────────────────────
DELETE n FROM provas_blocos_notas_lancadas n
INNER JOIN provas_blocos pb ON pb.id = n.bloco_id
WHERE pb.titulo LIKE 'ETF %';

DELETE FROM boletim_regras WHERE codigo LIKE 'etf-2026-%';

DELETE FROM provas_blocos WHERE titulo LIKE 'ETF %';

-- ── Presença / faltas / diário / planos ──────────────────────
DELETE FROM presenca_eventos WHERE id_externo LIKE 'etf-%';

DELETE FROM faltas_eventos
WHERE origem = 'diario'
  AND nome IN (
    'ETF Faltas 1º bimestre 2026',
    'ETF Faltas 2º bimestre 2026',
    'ETF Faltas 3º bimestre 2026',
    'ETF Faltas 4º bimestre 2026'
  );

DELETE df FROM diario_frequencias df
INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
WHERE da.conteudo_realizado LIKE 'ETF %' OR da.observacoes LIKE 'ETF-SEED%';

UPDATE diario_aulas
SET plano_aula_id = NULL, evento_bloco_id = NULL
WHERE conteudo_realizado LIKE 'ETF %' OR observacoes LIKE 'ETF-SEED%';

DELETE FROM diario_aulas
WHERE conteudo_realizado LIKE 'ETF %' OR observacoes LIKE 'ETF-SEED%';

DELETE FROM planos_aula WHERE titulo LIKE 'ETF %';

-- ── Matrícula, ficha, documentos, vínculos ───────────────────
DELETE d FROM alunos_documentos d
INNER JOIN alunos a ON a.id = d.aluno_id
WHERE a.nickname LIKE 'etf.%';

DELETE f FROM alunos_ficha_complementar f
INNER JOIN alunos a ON a.id = f.aluno_id
WHERE a.nickname LIKE 'etf.%';

UPDATE alunos SET responsavel_id = NULL WHERE nickname LIKE 'etf.%';

DELETE ar FROM alunos_responsaveis ar
INNER JOIN alunos a ON a.id = ar.aluno_id
WHERE a.nickname LIKE 'etf.%';

DELETE m FROM matricula m
INNER JOIN alunos a ON a.id = m.aluno_id
WHERE a.nickname LIKE 'etf.%';

DELETE FROM alunos WHERE nickname LIKE 'etf.%';

DELETE FROM responsaveis
WHERE email LIKE 'mae.etf.%@educateste.local'
   OR email LIKE 'pai.etf.%@educateste.local';

-- ── Grade / turmas / professores ─────────────────────────────
DELETE gh FROM grade_horaria gh
INNER JOIN turmas t ON t.id = gh.turma_id
WHERE t.ano_letivo = 2026 AND t.observacoes LIKE 'ETF %';

DELETE FROM turmas WHERE ano_letivo = 2026 AND observacoes LIKE 'ETF %';

DELETE FROM professores WHERE codigo_prof LIKE 'ETF-%';

-- ── Matrizes / salas / regras ────────────────────────────────
DELETE mcc FROM matrizes_curriculares_componentes mcc
INNER JOIN matrizes_curriculares mx ON mx.id = mcc.matriz_id
WHERE mx.codigo IN ('ETF-EF6', 'ETF-EF7', 'ETF-EF8', 'ETF-EF9');

DELETE FROM matrizes_curriculares WHERE codigo IN ('ETF-EF6', 'ETF-EF7', 'ETF-EF8', 'ETF-EF9');

DELETE FROM school_locations WHERE codigo LIKE 'ETF-SALA-%';

DELETE FROM regras_academicas WHERE codigo = 'ef2-educa-teste';

-- ── Curso / séries (só o FII deste seed) ─────────────────────
DELETE s FROM serie s
INNER JOIN curso c ON c.id = s.curso_id
WHERE c.nome = 'Ensino Fundamental II'
  AND c.descricao LIKE '%Educa Teste%'
  AND s.nome IN ('6º Ano EF', '7º Ano EF', '8º Ano EF', '9º Ano EF');

DELETE FROM curso WHERE nome = 'Ensino Fundamental II' AND descricao LIKE '%Educa Teste%';

-- Subtítulo: volta ao EM se ele ainda existir; senão remove o do FII.
UPDATE config_layout
SET config_value = 'Ensino Médio · 2026'
WHERE config_key = 'system_subtitle'
  AND EXISTS (SELECT 1 FROM turmas WHERE ano_letivo = 2026 AND observacoes LIKE 'ET %');

DELETE FROM config_layout
WHERE config_key = 'system_subtitle'
  AND config_value LIKE '%Fundamental%'
  AND NOT EXISTS (SELECT 1 FROM turmas WHERE ano_letivo = 2026 AND observacoes LIKE 'ET %');
