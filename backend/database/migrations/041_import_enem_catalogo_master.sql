-- Importação ENEM (enem_*) para catálogo simulados_* no banco master
-- Requisitos: tabelas enem_provas, enem_questoes, enem_alternativas, enem_questoes_arquivos
-- Objetivo: trazer TODOS os anos/provas do ENEM com vínculos corretos

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1) Garante banca ENEM
INSERT IGNORE INTO simulados_bancas (nome, slug, descricao, ativo, created_at, updated_at)
VALUES ('ENEM', 'enem', 'Exame Nacional do Ensino Médio', 1, NOW(), NOW());

SET @enem_banca_id := (
    SELECT id
    FROM simulados_bancas
    WHERE slug = 'enem'
    ORDER BY id
    LIMIT 1
);

-- 2) Limpa somente dados ENEM já importados no catálogo (evita duplicidade)
DELETE qc
FROM simulados_questoes_categorias qc
JOIN simulados_questoes q ON q.id = qc.questao_id
JOIN simulados_provas p ON p.id = q.prova_id
WHERE p.banca_id = @enem_banca_id;

DELETE qa
FROM simulados_questoes_arquivos qa
JOIN simulados_questoes q ON q.id = qa.questao_id
JOIN simulados_provas p ON p.id = q.prova_id
WHERE p.banca_id = @enem_banca_id;

DELETE a
FROM simulados_alternativas a
JOIN simulados_questoes q ON q.id = a.questao_id
JOIN simulados_provas p ON p.id = q.prova_id
WHERE p.banca_id = @enem_banca_id;

DELETE q
FROM simulados_questoes q
JOIN simulados_provas p ON p.id = q.prova_id
WHERE p.banca_id = @enem_banca_id;

DELETE FROM simulados_provas WHERE banca_id = @enem_banca_id;

-- 3) Offsets para preservar mapeamento old_id -> new_id sem colisão
SET @base_prova := (SELECT IFNULL(MAX(id), 0) FROM simulados_provas);
SET @base_questao := (SELECT IFNULL(MAX(id), 0) FROM simulados_questoes);

-- 4) Importa provas (TODOS os anos do ENEM)
INSERT INTO simulados_provas (
    id, banca_id, titulo, ano, fase, tipo, descricao, ativo, created_at, updated_at
)
SELECT
    (@base_prova + ep.id) AS id,
    @enem_banca_id AS banca_id,
    ep.title AS titulo,
    ep.year AS ano,
    NULL AS fase,
    'enem' AS tipo,
    'Importado de enem.sql' AS descricao,
    1 AS ativo,
    COALESCE(ep.created_at, NOW()) AS created_at,
    NOW() AS updated_at
FROM enem_provas ep
ORDER BY ep.year DESC, ep.id DESC;

-- 5) Categorias disciplina (uma por slug de disciplina)
INSERT INTO simulados_categorias (
    parent_id, tipo, nome, slug, descricao, ativo, ordem, created_at, updated_at
)
SELECT
    NULL AS parent_id,
    'disciplina' AS tipo,
    TRIM(ed.label) AS nome,
    CONCAT('enem-', LOWER(REPLACE(TRIM(ed.value), '_', '-'))) AS slug,
    'Importado do ENEM' AS descricao,
    1 AS ativo,
    0 AS ordem,
    NOW() AS created_at,
    NOW() AS updated_at
FROM (
    SELECT DISTINCT value, label
    FROM enem_disciplinas
    WHERE value IS NOT NULL AND value <> ''
) ed
LEFT JOIN simulados_categorias sc
    ON sc.tipo = 'disciplina'
   AND sc.slug = CONCAT('enem-', LOWER(REPLACE(TRIM(ed.value), '_', '-')))
WHERE sc.id IS NULL;

-- 6) Importa questões (old question id -> @base_questao + id)
INSERT INTO simulados_questoes (
    id, prova_id, indice, titulo, contexto, enunciado, tipo_questao, idioma, gabarito,
    comentario_resolucao, dificuldade, status, created_at, updated_at
)
SELECT
    (@base_questao + eq.id) AS id,
    (@base_prova + eq.exam_id) AS prova_id,
    COALESCE(eq.question_index, 0) AS indice,
    COALESCE(NULLIF(eq.title, ''), CONCAT('Questão ', eq.question_index, ' - ENEM ', eq.year)) AS titulo,
    eq.context AS contexto,
    CASE
        WHEN (eq.context IS NULL OR eq.context = '') AND (eq.alternatives_introduction IS NULL OR eq.alternatives_introduction = '')
            THEN NULL
        WHEN (eq.context IS NULL OR eq.context = '')
            THEN eq.alternatives_introduction
        WHEN (eq.alternatives_introduction IS NULL OR eq.alternatives_introduction = '')
            THEN eq.context
        ELSE CONCAT(eq.context, '\n\n', eq.alternatives_introduction)
    END AS enunciado,
    'objetiva' AS tipo_questao,
    CASE
        WHEN eq.language IS NULL OR eq.language = '' THEN 'pt'
        ELSE eq.language
    END AS idioma,
    eq.correct_alternative AS gabarito,
    NULL AS comentario_resolucao,
    NULL AS dificuldade,
    'published' AS status,
    COALESCE(eq.created_at, NOW()) AS created_at,
    NOW() AS updated_at
FROM enem_questoes eq
JOIN enem_provas ep ON ep.id = eq.exam_id
ORDER BY eq.id;

-- 7) Importa alternativas
INSERT INTO simulados_alternativas (
    questao_id, letra, texto, arquivo, is_correta, ordem, created_at, updated_at
)
SELECT
    (@base_questao + ea.question_id) AS questao_id,
    ea.letter AS letra,
    ea.text AS texto,
    ea.file AS arquivo,
    COALESCE(ea.is_correct, 0) AS is_correta,
    GREATEST(LOCATE(UPPER(COALESCE(ea.letter, '')), 'ABCDE') - 1, 0) AS ordem,
    NOW() AS created_at,
    NOW() AS updated_at
FROM enem_alternativas ea
JOIN enem_questoes eq ON eq.id = ea.question_id
ORDER BY ea.question_id, ea.id;

-- 8) Importa arquivos de questões (URLs existentes, ex.: enem.dev)
INSERT INTO simulados_questoes_arquivos (
    questao_id, arquivo_url, tipo_arquivo, ordem, legenda, created_at
)
SELECT
    (@base_questao + eqa.question_id) AS questao_id,
    eqa.file_url AS arquivo_url,
    'imagem' AS tipo_arquivo,
    0 AS ordem,
    NULL AS legenda,
    NOW() AS created_at
FROM enem_questoes_arquivos eqa
JOIN enem_questoes eq ON eq.id = eqa.question_id
WHERE eqa.file_url IS NOT NULL
  AND eqa.file_url <> ''
ORDER BY eqa.question_id, eqa.id;

-- 9) Vínculo questão -> categoria (disciplina)
INSERT IGNORE INTO simulados_questoes_categorias (questao_id, categoria_id, created_at)
SELECT
    (@base_questao + eq.id) AS questao_id,
    sc.id AS categoria_id,
    NOW() AS created_at
FROM enem_questoes eq
JOIN simulados_categorias sc
    ON sc.tipo = 'disciplina'
   AND sc.slug = CONCAT('enem-', LOWER(REPLACE(TRIM(eq.discipline), '_', '-')))
ORDER BY eq.id, sc.id;

SET FOREIGN_KEY_CHECKS = 1;
