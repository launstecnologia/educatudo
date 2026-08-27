-- Rollback do seed Escola Educa Teste (EM 2026).
-- Remove só o que este seed criou (prefixos et.%, ET-%, et-2026-%, @educateste.local).
-- Não apaga tipos de avaliação do catálogo (Prova Bimestral / Trabalho / Participação)
-- nem o catálogo de componentes curriculares.
-- Justificativa dos DELETE: dados sintéticos deste arquivo, isolados por prefixo/código.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '-03:00';

DROP PROCEDURE IF EXISTS seed_escola_teste_em;

-- ── Notas / eventos / boletim ────────────────────────────────
DELETE n FROM provas_blocos_notas_lancadas n
INNER JOIN provas_blocos pb ON pb.id = n.bloco_id
WHERE pb.titulo LIKE 'ET %';

DELETE FROM boletim_regras WHERE codigo LIKE 'et-2026-%';

DELETE FROM provas_blocos WHERE titulo LIKE 'ET %';

-- ── Presença / faltas / diário ───────────────────────────────
DELETE FROM presenca_eventos WHERE id_externo LIKE 'et-%';

DELETE FROM faltas_eventos
WHERE origem = 'diario'
  AND nome IN ('ET Faltas 1º bimestre 2026', 'ET Faltas 2º bimestre 2026');

DELETE df FROM diario_frequencias df
INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
WHERE da.conteudo_realizado LIKE 'ET %';

DELETE FROM diario_aulas WHERE conteudo_realizado LIKE 'ET %';

-- ── Matrícula, ficha, documentos, vínculos ───────────────────
DELETE d FROM alunos_documentos d
INNER JOIN alunos a ON a.id = d.aluno_id
WHERE a.nickname LIKE 'et.%';

DELETE f FROM alunos_ficha_complementar f
INNER JOIN alunos a ON a.id = f.aluno_id
WHERE a.nickname LIKE 'et.%';

UPDATE alunos SET responsavel_id = NULL WHERE nickname LIKE 'et.%';

DELETE ar FROM alunos_responsaveis ar
INNER JOIN alunos a ON a.id = ar.aluno_id
WHERE a.nickname LIKE 'et.%';

DELETE m FROM matricula m
INNER JOIN alunos a ON a.id = m.aluno_id
WHERE a.nickname LIKE 'et.%';

DELETE FROM alunos WHERE nickname LIKE 'et.%';

DELETE FROM responsaveis
WHERE email LIKE 'mae.et.%@educateste.local'
   OR email LIKE 'pai.et.%@educateste.local';

-- ── Grade / turmas / professores ─────────────────────────────
DELETE gh FROM grade_horaria gh
INNER JOIN turmas t ON t.id = gh.turma_id
WHERE t.ano_letivo = 2026 AND t.observacoes LIKE 'ET %';

DELETE FROM turmas WHERE ano_letivo = 2026 AND observacoes LIKE 'ET %';

DELETE FROM professores WHERE codigo_prof LIKE 'ET-%';

-- ── Matrizes / salas / regras ────────────────────────────────
DELETE mcc FROM matrizes_curriculares_componentes mcc
INNER JOIN matrizes_curriculares mx ON mx.id = mcc.matriz_id
WHERE mx.codigo IN ('ET-EM1', 'ET-EM2', 'ET-EM3');

DELETE FROM matrizes_curriculares WHERE codigo IN ('ET-EM1', 'ET-EM2', 'ET-EM3');

DELETE FROM school_locations WHERE codigo LIKE 'ET-SALA-%';

DELETE FROM regras_academicas WHERE codigo = 'em-educa-teste';

-- ── Curso / séries / calendário / ano (criados por este seed) ─
DELETE s FROM serie s
INNER JOIN curso c ON c.id = s.curso_id
WHERE c.nome = 'Ensino Médio'
  AND c.descricao LIKE '%Educa Teste%'
  AND s.nome IN ('1º Ano EM', '2º Ano EM', '3º Ano EM');

DELETE FROM curso WHERE nome = 'Ensino Médio' AND descricao LIKE '%Educa Teste%';

DELETE e FROM calendario_letivo_eventos e
INNER JOIN calendario_letivo c ON c.id = e.calendario_id
WHERE c.observacao = 'Calendário Escola Educa Teste';

DELETE FROM calendario_letivo WHERE observacao = 'Calendário Escola Educa Teste';

DELETE FROM ano_letivo WHERE ano = 2026;

-- ── Unidade / equipe / layout ────────────────────────────────
DELETE FROM unidades
WHERE nome = 'Escola Educa Teste'
   OR cnpj = '11.222.333/0001-81';

DELETE FROM usuarios WHERE email LIKE '%@educateste.local';

DELETE FROM config_layout
WHERE (config_key = 'system_title' AND config_value = 'Escola Educa Teste')
   OR (config_key = 'system_subtitle' AND config_value = 'Ensino Médio · 2026');
