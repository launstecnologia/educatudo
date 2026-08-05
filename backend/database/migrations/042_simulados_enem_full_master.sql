-- Importação ENEM FULL para catálogo central simulados_* (master)
-- Baseado no simulados_enem_full.sql com mapeamento seguro de IDs e classificação pedagógica.
-- Pré-requisito: tabelas enem_provas, enem_questoes, enem_alternativas, enem_questoes_arquivos já carregadas.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Garante banca ENEM
INSERT IGNORE INTO simulados_bancas (nome, slug, descricao, ativo, created_at, updated_at)
VALUES ('ENEM', 'enem', 'Exame Nacional do Ensino Médio', 1, NOW(), NOW());

SET @enem_banca_id := (
  SELECT id FROM simulados_bancas WHERE slug = 'enem' ORDER BY id LIMIT 1
);

-- Limpeza de catálogo ENEM (dados de provas/questões e vínculos)
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

-- Offsets para mapear IDs legados -> novos IDs sem colisão com outras bancas
SET @base_prova := (SELECT IFNULL(MAX(id), 0) FROM simulados_provas);
SET @base_questao := (SELECT IFNULL(MAX(id), 0) FROM simulados_questoes);

-- Provas
INSERT INTO simulados_provas
  (id, banca_id, titulo, ano, fase, tipo, descricao, ativo, created_at, updated_at)
SELECT
  (@base_prova + ep.id) AS id,
  @enem_banca_id AS banca_id,
  ep.title AS titulo,
  ep.year AS ano,
  NULL AS fase,
  'enem' AS tipo,
  'Importado via migration ENEM FULL' AS descricao,
  1 AS ativo,
  COALESCE(ep.created_at, NOW()) AS created_at,
  NOW() AS updated_at
FROM enem_provas ep
ORDER BY ep.year DESC, ep.id DESC;

-- Questões
INSERT INTO simulados_questoes
  (id, prova_id, indice, titulo, contexto, enunciado, tipo_questao, idioma, gabarito, comentario_resolucao, dificuldade, status, created_at, updated_at)
SELECT
  (@base_questao + q.id) AS id,
  (@base_prova + q.exam_id) AS prova_id,
  q.question_index AS indice,
  COALESCE(NULLIF(q.title, ''), CONCAT('Questão ', q.question_index, ' - ENEM ', q.year)) AS titulo,
  q.context AS contexto,
  q.alternatives_introduction AS enunciado,
  'objetiva' AS tipo_questao,
  CASE
    WHEN q.language = 'espanhol' THEN 'es'
    WHEN q.language = 'ingles' THEN 'en'
    WHEN q.language IS NULL OR q.language = '' THEN 'pt'
    ELSE q.language
  END AS idioma,
  q.correct_alternative AS gabarito,
  NULL AS comentario_resolucao,
  NULL AS dificuldade,
  'published' AS status,
  COALESCE(q.created_at, NOW()) AS created_at,
  NOW() AS updated_at
FROM enem_questoes q
JOIN enem_provas ep ON ep.id = q.exam_id
ORDER BY q.id;

-- Alternativas
INSERT INTO simulados_alternativas
  (questao_id, letra, texto, arquivo, is_correta, ordem, created_at, updated_at)
SELECT
  (@base_questao + a.question_id) AS questao_id,
  a.letter AS letra,
  a.text AS texto,
  a.file AS arquivo,
  COALESCE(a.is_correct, 0) AS is_correta,
  CASE a.letter
    WHEN 'A' THEN 1
    WHEN 'B' THEN 2
    WHEN 'C' THEN 3
    WHEN 'D' THEN 4
    WHEN 'E' THEN 5
    ELSE 99
  END AS ordem,
  NOW() AS created_at,
  NOW() AS updated_at
FROM enem_alternativas a
JOIN enem_questoes q ON q.id = a.question_id
ORDER BY a.question_id, a.id;

-- Arquivos
INSERT INTO simulados_questoes_arquivos
  (questao_id, arquivo_url, tipo_arquivo, ordem, legenda, created_at)
SELECT
  (@base_questao + qa.question_id) AS questao_id,
  qa.file_url AS arquivo_url,
  CASE
    WHEN LOWER(qa.file_url) REGEXP '\\.(png|jpg|jpeg|gif|webp|svg)$' THEN 'imagem'
    ELSE 'arquivo'
  END AS tipo_arquivo,
  qa.id AS ordem,
  NULL AS legenda,
  NOW() AS created_at
FROM enem_questoes_arquivos qa
JOIN enem_questoes q ON q.id = qa.question_id
WHERE qa.file_url IS NOT NULL AND qa.file_url <> ''
ORDER BY qa.question_id, qa.id;

-- Classificação pedagógica (baseada no dump full)
DROP TEMPORARY TABLE IF EXISTS tmp_enem_classificacao;
CREATE TEMPORARY TABLE tmp_enem_classificacao AS
SELECT
  q.id AS questao_legacy_id,
  CASE
    WHEN q.discipline = 'linguagens' THEN 'Linguagens'
    WHEN q.discipline = 'ciencias-humanas' THEN 'Ciências Humanas'
    WHEN q.discipline = 'ciencias-natureza' THEN 'Ciências da Natureza'
    WHEN q.discipline = 'matematica' THEN 'Matemática'
    ELSE 'Geral'
  END AS area_nome,
  CASE
    WHEN q.discipline = 'linguagens' THEN 'Linguagens'
    WHEN q.discipline = 'ciencias-humanas' THEN 'Ciências Humanas'
    WHEN q.discipline = 'ciencias-natureza' THEN 'Ciências da Natureza'
    WHEN q.discipline = 'matematica' THEN 'Matemática'
    ELSE 'Geral'
  END AS disciplina_nome,
  CASE
    WHEN q.discipline = 'linguagens' THEN 'Interpretação e Linguagem'
    WHEN q.discipline = 'ciencias-humanas' THEN 'Humanas e Sociedade'
    WHEN q.discipline = 'ciencias-natureza' THEN 'Fenômenos da Natureza'
    WHEN q.discipline = 'matematica' THEN 'Raciocínio Matemático'
    ELSE 'Classificação Geral'
  END AS tema_nome,
  CASE
    WHEN q.discipline = 'linguagens'
      AND LOWER(CONCAT_WS(' ', q.title, q.context, q.alternatives_introduction))
          REGEXP 'charge|cartaz|campanha|publicit[aá]ria|propaganda'
      THEN 'Leitura de Texto Multimodal'
    WHEN q.discipline = 'linguagens'
      AND LOWER(CONCAT_WS(' ', q.title, q.context, q.alternatives_introduction))
          REGEXP 'libras|varia[cç][aã]o lingu[ií]stica|gram[aá]tica'
      THEN 'Variação e Uso da Língua'
    WHEN q.discipline = 'linguagens' THEN 'Compreensão Leitora'
    WHEN q.discipline = 'ciencias-humanas' THEN 'Interpretação em Humanas'
    WHEN q.discipline = 'ciencias-natureza' THEN 'Interpretação em Natureza'
    WHEN q.discipline = 'matematica' THEN 'Resolução de Problemas'
    ELSE 'Classificação Geral'
  END AS subtema_nome
FROM enem_questoes q;

-- Evita conflito "Illegal mix of collations" ao comparar com simulados_categorias
ALTER TABLE tmp_enem_classificacao
  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Áreas
INSERT INTO simulados_categorias (parent_id, tipo, nome, slug, descricao, ativo, ordem, created_at, updated_at)
SELECT DISTINCT
  NULL,
  'area',
  c.area_nome,
  CONCAT('enem-area-', LOWER(REPLACE(REPLACE(REPLACE(c.area_nome, ' ', '-'), 'ã', 'a'), 'ç', 'c'))),
  'Área pedagógica importada do ENEM',
  1,
  0,
  NOW(),
  NOW()
FROM tmp_enem_classificacao c
WHERE NOT EXISTS (
  SELECT 1 FROM simulados_categorias x
  WHERE x.tipo = 'area'
    AND x.parent_id IS NULL
    AND x.nome COLLATE utf8mb4_unicode_ci = c.area_nome COLLATE utf8mb4_unicode_ci
);

-- Disciplinas
INSERT INTO simulados_categorias (parent_id, tipo, nome, slug, descricao, ativo, ordem, created_at, updated_at)
SELECT DISTINCT
  a.id,
  'disciplina',
  c.disciplina_nome,
  CONCAT('enem-disc-', LOWER(REPLACE(REPLACE(REPLACE(c.disciplina_nome, ' ', '-'), 'ã', 'a'), 'ç', 'c'))),
  CONCAT('Disciplina inferida para área ', c.area_nome),
  1,
  0,
  NOW(),
  NOW()
FROM tmp_enem_classificacao c
JOIN simulados_categorias a
  ON a.tipo = 'area'
 AND a.nome COLLATE utf8mb4_unicode_ci = c.area_nome COLLATE utf8mb4_unicode_ci
WHERE NOT EXISTS (
  SELECT 1 FROM simulados_categorias x
  WHERE x.tipo = 'disciplina'
    AND x.parent_id = a.id
    AND x.nome COLLATE utf8mb4_unicode_ci = c.disciplina_nome COLLATE utf8mb4_unicode_ci
);

-- Temas
INSERT INTO simulados_categorias (parent_id, tipo, nome, slug, descricao, ativo, ordem, created_at, updated_at)
SELECT DISTINCT
  d.id,
  'tema',
  c.tema_nome,
  CONCAT('enem-tema-', LOWER(REPLACE(REPLACE(REPLACE(c.tema_nome, ' ', '-'), 'ã', 'a'), 'ç', 'c'))),
  CONCAT('Tema inferido para disciplina ', c.disciplina_nome),
  1,
  0,
  NOW(),
  NOW()
FROM tmp_enem_classificacao c
JOIN simulados_categorias a
  ON a.tipo = 'area'
 AND a.nome COLLATE utf8mb4_unicode_ci = c.area_nome COLLATE utf8mb4_unicode_ci
JOIN simulados_categorias d
  ON d.tipo = 'disciplina'
 AND d.parent_id = a.id
 AND d.nome COLLATE utf8mb4_unicode_ci = c.disciplina_nome COLLATE utf8mb4_unicode_ci
WHERE NOT EXISTS (
  SELECT 1 FROM simulados_categorias x
  WHERE x.tipo = 'tema'
    AND x.parent_id = d.id
    AND x.nome COLLATE utf8mb4_unicode_ci = c.tema_nome COLLATE utf8mb4_unicode_ci
);

-- Subtemas
INSERT INTO simulados_categorias (parent_id, tipo, nome, slug, descricao, ativo, ordem, created_at, updated_at)
SELECT DISTINCT
  t.id,
  'subtema',
  c.subtema_nome,
  CONCAT('enem-subtema-', LOWER(REPLACE(REPLACE(REPLACE(c.subtema_nome, ' ', '-'), 'ã', 'a'), 'ç', 'c'))),
  CONCAT('Subtema inferido para tema ', c.tema_nome),
  1,
  0,
  NOW(),
  NOW()
FROM tmp_enem_classificacao c
JOIN simulados_categorias a
  ON a.tipo = 'area'
 AND a.nome COLLATE utf8mb4_unicode_ci = c.area_nome COLLATE utf8mb4_unicode_ci
JOIN simulados_categorias d
  ON d.tipo = 'disciplina'
 AND d.parent_id = a.id
 AND d.nome COLLATE utf8mb4_unicode_ci = c.disciplina_nome COLLATE utf8mb4_unicode_ci
JOIN simulados_categorias t
  ON t.tipo = 'tema'
 AND t.parent_id = d.id
 AND t.nome COLLATE utf8mb4_unicode_ci = c.tema_nome COLLATE utf8mb4_unicode_ci
WHERE NOT EXISTS (
  SELECT 1 FROM simulados_categorias x
  WHERE x.tipo = 'subtema'
    AND x.parent_id = t.id
    AND x.nome COLLATE utf8mb4_unicode_ci = c.subtema_nome COLLATE utf8mb4_unicode_ci
);

-- Vínculo de questão com disciplina/tema/subtema
INSERT IGNORE INTO simulados_questoes_categorias (questao_id, categoria_id, created_at)
SELECT (@base_questao + c.questao_legacy_id), d.id, NOW()
FROM tmp_enem_classificacao c
JOIN simulados_categorias a
  ON a.tipo = 'area'
 AND a.nome COLLATE utf8mb4_unicode_ci = c.area_nome COLLATE utf8mb4_unicode_ci
JOIN simulados_categorias d
  ON d.tipo = 'disciplina'
 AND d.parent_id = a.id
 AND d.nome COLLATE utf8mb4_unicode_ci = c.disciplina_nome COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO simulados_questoes_categorias (questao_id, categoria_id, created_at)
SELECT (@base_questao + c.questao_legacy_id), t.id, NOW()
FROM tmp_enem_classificacao c
JOIN simulados_categorias a
  ON a.tipo = 'area'
 AND a.nome COLLATE utf8mb4_unicode_ci = c.area_nome COLLATE utf8mb4_unicode_ci
JOIN simulados_categorias d
  ON d.tipo = 'disciplina'
 AND d.parent_id = a.id
 AND d.nome COLLATE utf8mb4_unicode_ci = c.disciplina_nome COLLATE utf8mb4_unicode_ci
JOIN simulados_categorias t
  ON t.tipo = 'tema'
 AND t.parent_id = d.id
 AND t.nome COLLATE utf8mb4_unicode_ci = c.tema_nome COLLATE utf8mb4_unicode_ci;

INSERT IGNORE INTO simulados_questoes_categorias (questao_id, categoria_id, created_at)
SELECT (@base_questao + c.questao_legacy_id), s.id, NOW()
FROM tmp_enem_classificacao c
JOIN simulados_categorias a
  ON a.tipo = 'area'
 AND a.nome COLLATE utf8mb4_unicode_ci = c.area_nome COLLATE utf8mb4_unicode_ci
JOIN simulados_categorias d
  ON d.tipo = 'disciplina'
 AND d.parent_id = a.id
 AND d.nome COLLATE utf8mb4_unicode_ci = c.disciplina_nome COLLATE utf8mb4_unicode_ci
JOIN simulados_categorias t
  ON t.tipo = 'tema'
 AND t.parent_id = d.id
 AND t.nome COLLATE utf8mb4_unicode_ci = c.tema_nome COLLATE utf8mb4_unicode_ci
JOIN simulados_categorias s
  ON s.tipo = 'subtema'
 AND s.parent_id = t.id
 AND s.nome COLLATE utf8mb4_unicode_ci = c.subtema_nome COLLATE utf8mb4_unicode_ci;

DROP TEMPORARY TABLE IF EXISTS tmp_enem_classificacao;

SET FOREIGN_KEY_CHECKS = 1;
