-- =============================================================================
-- Importação SEB Ribeirânia (tenant) — estrutura, turmas, alunos e grade
-- Arquivo UTF-8 (NFC). O PDO do Master já conecta com charset=utf8mb4.
-- Idempotente: INSERT ... WHERE NOT EXISTS.
-- Senha padrão alunos/professores: 123456 (primeiro acesso).
--
-- Master: /master/migrations → escola SEB → Escolher → marcar só este arquivo.
-- Não usar "Executar todas" (este arquivo é pulado no bootstrap de escola nova).
-- Rode antes as migrations de schema pendentes da escola.
-- =============================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET character_set_client = utf8mb4;
SET character_set_connection = utf8mb4;
SET character_set_results = utf8mb4;
SET time_zone = '-03:00';

-- Aborta se o banco atual não for da SEB (evita importar em outra escola).
SET @db_atual := DATABASE();
SET @eh_seb := (@db_atual LIKE '%seb%' OR @db_atual LIKE '%ribeirania%');
SET @stmt_guard := IF(@eh_seb, 'SELECT 1', CONCAT('SELECT * FROM `ERRO_importe_somente_na_SEB_banco_atual_e_', IFNULL(@db_atual, 'vazio'), '`'));
PREPARE stmt_guard FROM @stmt_guard;
EXECUTE stmt_guard;
DEALLOCATE PREPARE stmt_guard;

-- 1) Unidade (dados da escola)
INSERT INTO unidades (nome, tipo, razao_social, cnpj, endereco, numero, bairro, cidade, uf, cep, diretor_nome, secretario_nome, ativo)
SELECT 'ESCOLA SEB - UNIDADE RIBEIRÂNIA', 'matriz', 'Sistema Educacional Brasileiro', '33.268.567/0009-50', 'Rua Abraão Issa Halack', '320', 'Ribeirânia', 'Ribeirão Preto', 'SP', '14096160', 'Célia Cristina Zanchetta Borges', 'Lucilene Margarete V. Moraes Sarmento', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM unidades WHERE cnpj = '33.268.567/0009-50' OR nome = 'ESCOLA SEB - UNIDADE RIBEIRÂNIA');
UPDATE unidades SET
  nome = 'ESCOLA SEB - UNIDADE RIBEIRÂNIA',
  razao_social = 'Sistema Educacional Brasileiro',
  cnpj = '33.268.567/0009-50',
  endereco = 'Rua Abraão Issa Halack',
  numero = '320',
  bairro = 'Ribeirânia',
  cidade = 'Ribeirão Preto',
  uf = 'SP',
  cep = '14096-160',
  diretor_nome = 'Célia Cristina Zanchetta Borges',
  secretario_nome = 'Lucilene Margarete V. Moraes Sarmento'
WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM unidades) x);

-- 2) Ano letivo
INSERT INTO ano_letivo (ano, data_inicio, data_fim, ativo)
SELECT 2026, '2026-01-27', '2026-12-18', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM ano_letivo WHERE ano = 2026);

-- 3) Cursos (tabela curso da estrutura nova)
INSERT INTO curso (nome, tipo, possui_serie, descricao, ativo, ordem)
SELECT 'Fundamental II Regular', 'regular', 1, NULL, 1, 10 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM curso WHERE LOWER(TRIM(nome)) = LOWER('Fundamental II Regular'));
INSERT INTO curso (nome, tipo, possui_serie, descricao, ativo, ordem)
SELECT 'Ensino Médio Regular', 'regular', 1, NULL, 1, 20 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM curso WHERE LOWER(TRIM(nome)) = LOWER('Ensino Médio Regular'));
INSERT INTO curso (nome, tipo, possui_serie, descricao, ativo, ordem)
SELECT 'Fundamental II Bilingue', 'regular', 1, NULL, 1, 30 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM curso WHERE LOWER(TRIM(nome)) = LOWER('Fundamental II Bilingue'));

-- 4) Séries
INSERT INTO serie (curso_id, nome, ordem, ativo)
SELECT c.id, '8º Ano', 10, 1 FROM curso c
WHERE c.nome = 'Fundamental II Regular'
  AND NOT EXISTS (SELECT 1 FROM serie s WHERE s.curso_id = c.id AND s.nome = '8º Ano');
INSERT INTO serie (curso_id, nome, ordem, ativo)
SELECT c.id, '9º Ano', 20, 1 FROM curso c
WHERE c.nome = 'Fundamental II Regular'
  AND NOT EXISTS (SELECT 1 FROM serie s WHERE s.curso_id = c.id AND s.nome = '9º Ano');
INSERT INTO serie (curso_id, nome, ordem, ativo)
SELECT c.id, '1ª Série', 30, 1 FROM curso c
WHERE c.nome = 'Ensino Médio Regular'
  AND NOT EXISTS (SELECT 1 FROM serie s WHERE s.curso_id = c.id AND s.nome = '1ª Série');
INSERT INTO serie (curso_id, nome, ordem, ativo)
SELECT c.id, '2ª Série', 40, 1 FROM curso c
WHERE c.nome = 'Ensino Médio Regular'
  AND NOT EXISTS (SELECT 1 FROM serie s WHERE s.curso_id = c.id AND s.nome = '2ª Série');
INSERT INTO serie (curso_id, nome, ordem, ativo)
SELECT c.id, '8th grade', 50, 1 FROM curso c
WHERE c.nome = 'Fundamental II Bilingue'
  AND NOT EXISTS (SELECT 1 FROM serie s WHERE s.curso_id = c.id AND s.nome = '8th grade');

-- 5) Componentes curriculares (tabela materias)
--    Atualiza código/sigla se o catálogo padrão já tiver o mesmo nome.
UPDATE materias SET codigo = '3', sigla = 'GEO', area_conhecimento = 'ciencias_humanas', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 1
WHERE LOWER(TRIM(nome)) = LOWER('Geografia');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Geografia', '3', 'GEO', 'ciencias_humanas', 'formacao_geral', NULL, '#EF4444', 1, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Geografia'));

UPDATE materias SET codigo = '4', sigla = 'HIS', area_conhecimento = 'ciencias_humanas', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 2
WHERE LOWER(TRIM(nome)) = LOWER('História');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'História', '4', 'HIS', 'ciencias_humanas', 'formacao_geral', NULL, '#EF4444', 2, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('História'));

UPDATE materias SET codigo = '5', sigla = 'MAT', area_conhecimento = 'matematica', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 3
WHERE LOWER(TRIM(nome)) = LOWER('Matemática');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Matemática', '5', 'MAT', 'matematica', 'formacao_geral', NULL, '#F59E0B', 3, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Matemática'));

UPDATE materias SET codigo = '9', sigla = 'EDF', area_conhecimento = 'linguagens', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 4
WHERE LOWER(TRIM(nome)) = LOWER('Educação Física');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Educação Física', '9', 'EDF', 'linguagens', 'formacao_geral', NULL, '#3B82F6', 4, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Educação Física'));

UPDATE materias SET codigo = '26', sigla = 'POR', area_conhecimento = 'linguagens', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 5
WHERE LOWER(TRIM(nome)) = LOWER('Língua Portuguesa');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Língua Portuguesa', '26', 'POR', 'linguagens', 'formacao_geral', NULL, '#3B82F6', 5, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Língua Portuguesa'));

UPDATE materias SET codigo = '72', sigla = 'RED', area_conhecimento = 'linguagens', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 6
WHERE LOWER(TRIM(nome)) = LOWER('Redação');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Redação', '72', 'RED', 'linguagens', 'formacao_geral', NULL, '#3B82F6', 6, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Redação'));

UPDATE materias SET codigo = '288', sigla = 'CIE', area_conhecimento = 'ciencias_natureza', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 7
WHERE LOWER(TRIM(nome)) = LOWER('Ciências');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Ciências', '288', 'CIE', 'ciencias_natureza', 'formacao_geral', NULL, '#10B981', 7, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Ciências'));

UPDATE materias SET codigo = '248', sigla = 'ART', area_conhecimento = 'linguagens', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 8
WHERE LOWER(TRIM(nome)) = LOWER('Arte');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Arte', '248', 'ART', 'linguagens', 'formacao_geral', NULL, '#3B82F6', 8, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Arte'));

UPDATE materias SET codigo = '304', sigla = 'LIN', area_conhecimento = 'linguagens', tipo = 'lingua_adicional',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 9
WHERE LOWER(TRIM(nome)) = LOWER('Língua Inglesa');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Língua Inglesa', '304', 'LIN', 'linguagens', 'lingua_adicional', NULL, '#3B82F6', 9, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Língua Inglesa'));

UPDATE materias SET codigo = '4759', sigla = 'SOC', area_conhecimento = 'outra', tipo = 'itinerario_formativo',
  permite_avaliacao = 0, permite_frequencia = 1, permite_diario = 0, ativo = 1, ordem = 10
WHERE LOWER(TRIM(nome)) = LOWER('Socioemocional');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Socioemocional', '4759', 'SOC', 'outra', 'itinerario_formativo', NULL, '#8B5CF6', 10, 0, 1, 1, 0, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Socioemocional'));

UPDATE materias SET codigo = '4564', sigla = 'COD', area_conhecimento = 'matematica', tipo = 'complementar',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 11
WHERE LOWER(TRIM(nome)) = LOWER('Coding');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Coding', '4564', 'COD', 'matematica', 'complementar', NULL, '#F59E0B', 11, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Coding'));

UPDATE materias SET codigo = '4565', sigla = 'LAN', area_conhecimento = 'linguagens', tipo = 'lingua_adicional',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 12
WHERE LOWER(TRIM(nome)) = LOWER('Language');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Language', '4565', 'LAN', 'linguagens', 'lingua_adicional', NULL, '#3B82F6', 12, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Language'));

UPDATE materias SET codigo = '4566', sigla = 'MTH', area_conhecimento = 'matematica', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 13
WHERE LOWER(TRIM(nome)) = LOWER('Math');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Math', '4566', 'MTH', 'matematica', 'formacao_geral', NULL, '#F59E0B', 13, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Math'));

UPDATE materias SET codigo = '4567', sigla = 'SCI', area_conhecimento = 'ciencias_natureza', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 14
WHERE LOWER(TRIM(nome)) = LOWER('Science/Steam');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Science/Steam', '4567', 'SCI', 'ciencias_natureza', 'formacao_geral', NULL, '#10B981', 14, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Science/Steam'));

UPDATE materias SET codigo = '4568', sigla = 'S&E', area_conhecimento = 'outra', tipo = 'itinerario_formativo',
  permite_avaliacao = 0, permite_frequencia = 1, permite_diario = 0, ativo = 1, ordem = 15
WHERE LOWER(TRIM(nome)) = LOWER('Social & Emotional');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Social & Emotional', '4568', 'S&E', 'outra', 'itinerario_formativo', NULL, '#8B5CF6', 15, 0, 1, 1, 0, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Social & Emotional'));

UPDATE materias SET codigo = '4571', sigla = 'GHY', area_conhecimento = 'ciencias_humanas', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 16
WHERE LOWER(TRIM(nome)) = LOWER('Geography');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Geography', '4571', 'GHY', 'ciencias_humanas', 'formacao_geral', NULL, '#EF4444', 16, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Geography'));

UPDATE materias SET codigo = '4637', sigla = 'ARY', area_conhecimento = 'linguagens', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 17
WHERE LOWER(TRIM(nome)) = LOWER('Art');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Art', '4637', 'ARY', 'linguagens', 'formacao_geral', NULL, '#3B82F6', 17, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Art'));

UPDATE materias SET codigo = '4863', sigla = 'HIY', area_conhecimento = 'ciencias_humanas', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 18
WHERE LOWER(TRIM(nome)) = LOWER('History');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'History', '4863', 'HIY', 'ciencias_humanas', 'formacao_geral', NULL, '#EF4444', 18, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('History'));

UPDATE materias SET codigo = '6', sigla = 'QUI', area_conhecimento = 'ciencias_natureza', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 19
WHERE LOWER(TRIM(nome)) = LOWER('Química');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Química', '6', 'QUI', 'ciencias_natureza', 'formacao_geral', NULL, '#10B981', 19, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Química'));

UPDATE materias SET codigo = '7', sigla = 'FIS', area_conhecimento = 'ciencias_natureza', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 20
WHERE LOWER(TRIM(nome)) = LOWER('Física');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Física', '7', 'FIS', 'ciencias_natureza', 'formacao_geral', NULL, '#10B981', 20, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Física'));

UPDATE materias SET codigo = '45', sigla = 'FIL', area_conhecimento = 'ciencias_humanas', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 21
WHERE LOWER(TRIM(nome)) = LOWER('Filosofia');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Filosofia', '45', 'FIL', 'ciencias_humanas', 'formacao_geral', NULL, '#EF4444', 21, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Filosofia'));

UPDATE materias SET codigo = '98', sigla = 'BIO', area_conhecimento = 'ciencias_natureza', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 22
WHERE LOWER(TRIM(nome)) = LOWER('Biologia');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Biologia', '98', 'BIO', 'ciencias_natureza', 'formacao_geral', NULL, '#10B981', 22, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Biologia'));

UPDATE materias SET codigo = '988', sigla = 'SLG', area_conhecimento = 'ciencias_humanas', tipo = 'formacao_geral',
  permite_avaliacao = 1, permite_frequencia = 1, permite_diario = 1, ativo = 1, ordem = 23
WHERE LOWER(TRIM(nome)) = LOWER('Sociologia');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Sociologia', '988', 'SLG', 'ciencias_humanas', 'formacao_geral', NULL, '#EF4444', 23, 1, 1, 1, 1, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Sociologia'));

UPDATE materias SET codigo = '920', sigla = 'ESP', area_conhecimento = 'outra', tipo = 'itinerario_formativo',
  permite_avaliacao = 0, permite_frequencia = 1, permite_diario = 0, ativo = 1, ordem = 24
WHERE LOWER(TRIM(nome)) = LOWER('Língua Espanhola');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Língua Espanhola', '920', 'ESP', 'outra', 'itinerario_formativo', NULL, '#8B5CF6', 24, 0, 1, 1, 0, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Língua Espanhola'));

UPDATE materias SET codigo = '4794', sigla = 'Mun', area_conhecimento = 'outra', tipo = 'itinerario_formativo',
  permite_avaliacao = 0, permite_frequencia = 1, permite_diario = 0, ativo = 1, ordem = 25
WHERE LOWER(TRIM(nome)) = LOWER('Cidadão do Mundo');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Cidadão do Mundo', '4794', 'Mun', 'outra', 'itinerario_formativo', NULL, '#8B5CF6', 25, 0, 1, 1, 0, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Cidadão do Mundo'));

UPDATE materias SET codigo = '4793', sigla = 'INO', area_conhecimento = 'outra', tipo = 'itinerario_formativo',
  permite_avaliacao = 0, permite_frequencia = 1, permite_diario = 0, ativo = 1, ordem = 26
WHERE LOWER(TRIM(nome)) = LOWER('Saúde e Inovação');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Saúde e Inovação', '4793', 'INO', 'outra', 'itinerario_formativo', NULL, '#8B5CF6', 26, 0, 1, 1, 0, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Saúde e Inovação'));

UPDATE materias SET codigo = '4751', sigla = 'STA', area_conhecimento = 'outra', tipo = 'itinerario_formativo',
  permite_avaliacao = 0, permite_frequencia = 1, permite_diario = 0, ativo = 1, ordem = 27
WHERE LOWER(TRIM(nome)) = LOWER('Criação de Startups');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Criação de Startups', '4751', 'STA', 'outra', 'itinerario_formativo', NULL, '#8B5CF6', 27, 0, 1, 1, 0, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Criação de Startups'));

UPDATE materias SET codigo = '5738', sigla = 'SUS', area_conhecimento = 'outra', tipo = 'itinerario_formativo',
  permite_avaliacao = 0, permite_frequencia = 1, permite_diario = 0, ativo = 1, ordem = 28
WHERE LOWER(TRIM(nome)) = LOWER('Cidadania e Sustentabilidade');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Cidadania e Sustentabilidade', '5738', 'SUS', 'outra', 'itinerario_formativo', NULL, '#8B5CF6', 28, 0, 1, 1, 0, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Cidadania e Sustentabilidade'));

UPDATE materias SET codigo = '4980', sigla = 'MKT', area_conhecimento = 'outra', tipo = 'itinerario_formativo',
  permite_avaliacao = 0, permite_frequencia = 1, permite_diario = 0, ativo = 1, ordem = 29
WHERE LOWER(TRIM(nome)) = LOWER('Marketing Digital');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Marketing Digital', '4980', 'MKT', 'outra', 'itinerario_formativo', NULL, '#8B5CF6', 29, 0, 1, 1, 0, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Marketing Digital'));

UPDATE materias SET codigo = 'FAZENDOASCONTASPA', sigla = 'FAZENDO AS', area_conhecimento = 'outra', tipo = 'itinerario_formativo',
  permite_avaliacao = 0, permite_frequencia = 1, permite_diario = 0, ativo = 1, ordem = 30
WHERE LOWER(TRIM(nome)) = LOWER('Fazendo as Contas Para o Futuro');
INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, descricao, cor, ordem,
  permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo,
  etapa_infantil, etapa_fund_i, etapa_fund_ii, etapa_medio)
SELECT 'Fazendo as Contas Para o Futuro', 'FAZENDOASCONTASPA', 'FAZENDO AS', 'outra', 'itinerario_formativo', NULL, '#8B5CF6', 30, 0, 1, 1, 0, 1,
  'nao_aplica', 'nao_aplica', 'obrigatoria', 'oferta' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM materias WHERE LOWER(TRIM(nome)) = LOWER('Fazendo as Contas Para o Futuro'));

-- 6) Matrizes curriculares
INSERT INTO matrizes_curriculares (nome, codigo, curso_id, serie_id, modalidade, turno,
  carga_horaria_anual_prevista, dias_letivos_previstos, duracao_padrao_aula_minutos, ativo)
SELECT 'Ribeirânia 2026 - 8º Ano', 'RIB-2026-8ANO', c.id, s.id, NULL, 'Matutino', 1160.0, 200, 45, 1
FROM curso c
JOIN serie s ON s.curso_id = c.id AND s.nome = '8º Ano'
WHERE c.nome = 'Fundamental II Regular'
  AND NOT EXISTS (SELECT 1 FROM matrizes_curriculares m WHERE m.codigo = 'RIB-2026-8ANO' OR m.nome = 'Ribeirânia 2026 - 8º Ano');

INSERT INTO matrizes_curriculares (nome, codigo, curso_id, serie_id, modalidade, turno,
  carga_horaria_anual_prevista, dias_letivos_previstos, duracao_padrao_aula_minutos, ativo)
SELECT 'Ribeirânia 2026 - 9º Ano', 'RIB-2026-9ANO', c.id, s.id, NULL, 'Matutino', 1160.0, 200, 45, 1
FROM curso c
JOIN serie s ON s.curso_id = c.id AND s.nome = '9º Ano'
WHERE c.nome = 'Fundamental II Regular'
  AND NOT EXISTS (SELECT 1 FROM matrizes_curriculares m WHERE m.codigo = 'RIB-2026-9ANO' OR m.nome = 'Ribeirânia 2026 - 9º Ano');

INSERT INTO matrizes_curriculares (nome, codigo, curso_id, serie_id, modalidade, turno,
  carga_horaria_anual_prevista, dias_letivos_previstos, duracao_padrao_aula_minutos, ativo)
SELECT 'Ribeirânia 2026 - 8th', 'RIB-2026-8TH', c.id, s.id, NULL, 'Matutino', 1600.0, 200, 45, 1
FROM curso c
JOIN serie s ON s.curso_id = c.id AND s.nome = '8th grade'
WHERE c.nome = 'Fundamental II Bilingue'
  AND NOT EXISTS (SELECT 1 FROM matrizes_curriculares m WHERE m.codigo = 'RIB-2026-8TH' OR m.nome = 'Ribeirânia 2026 - 8th');

INSERT INTO matrizes_curriculares (nome, codigo, curso_id, serie_id, modalidade, turno,
  carga_horaria_anual_prevista, dias_letivos_previstos, duracao_padrao_aula_minutos, ativo)
SELECT 'Ribeirânia 2026 - 1ª Série', 'RIB-2026-1SER', c.id, s.id, NULL, 'Matutino', 1560.0, 200, 45, 1
FROM curso c
JOIN serie s ON s.curso_id = c.id AND s.nome = '1ª Série'
WHERE c.nome = 'Ensino Médio Regular'
  AND NOT EXISTS (SELECT 1 FROM matrizes_curriculares m WHERE m.codigo = 'RIB-2026-1SER' OR m.nome = 'Ribeirânia 2026 - 1ª Série');

INSERT INTO matrizes_curriculares (nome, codigo, curso_id, serie_id, modalidade, turno,
  carga_horaria_anual_prevista, dias_letivos_previstos, duracao_padrao_aula_minutos, ativo)
SELECT 'Ribeirânia 2026 - 2ª Série', 'RIB-2026-2SER', c.id, s.id, NULL, 'Matutino', 1560.0, 200, 45, 1
FROM curso c
JOIN serie s ON s.curso_id = c.id AND s.nome = '2ª Série'
WHERE c.nome = 'Ensino Médio Regular'
  AND NOT EXISTS (SELECT 1 FROM matrizes_curriculares m WHERE m.codigo = 'RIB-2026-2SER' OR m.nome = 'Ribeirânia 2026 - 2ª Série');

-- 7) Componentes de cada matriz
INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 3, 1, 1, 1
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Geografia') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 3, 1, 2, 2
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('História') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 5, 1, 3, 3
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Matemática') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 2, 1, 4, 4
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Educação Física') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 7, 1, 5, 5
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Língua Portuguesa') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 6, 6
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Arte') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 4, 1, 7, 7
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Ciências') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 3, 1, 8, 8
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Língua Inglesa') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 9, 9
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Socioemocional') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 3, 1, 10, 10
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Geografia') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 9º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 3, 1, 11, 11
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('História') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 9º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 5, 1, 12, 12
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Matemática') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 9º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 2, 1, 13, 13
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Educação Física') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 9º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 7, 1, 14, 14
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Língua Portuguesa') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 9º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 15, 15
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Arte') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 9º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 4, 1, 16, 16
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Ciências') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 9º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 3, 1, 17, 17
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Língua Inglesa') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 9º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 18, 18
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Socioemocional') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 9º Ano'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 2, 1, 19, 19
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Geografia') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 2, 1, 20, 20
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('História') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 4, 1, 21, 21
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Matemática') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 2, 1, 22, 22
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Educação Física') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 7, 1, 23, 23
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Língua Portuguesa') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 24, 24
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Arte') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 2, 1, 25, 25
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Ciências') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 4, 1, 26, 26
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Língua Inglesa') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 27, 27
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Socioemocional') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 5, 1, 28, 28
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Language') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 29, 29
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Art') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 30, 30
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Math') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 31, 31
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Science/Steam') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 32, 32
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Geography') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 33, 33
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('History') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 34, 34
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Coding') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 8th'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 8, 1, 35, 35
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Língua Portuguesa') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 36, 36
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Língua Inglesa') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 37, 37
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Arte') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 2, 1, 38, 38
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Educação Física') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 7, 1, 39, 39
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Matemática') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 3, 1, 40, 40
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Biologia') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 3, 1, 41, 41
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Física') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 3, 1, 42, 42
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Química') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 2, 1, 43, 43
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('História') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 2, 1, 44, 44
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Geografia') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 45, 45
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Filosofia') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 46, 46
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Sociologia') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 47, 47
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Socioemocional') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 48, 48
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Língua Espanhola') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 49, 49
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Cidadão do Mundo') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 50, 50
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Saúde e Inovação') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 51, 51
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Criação de Startups') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 1ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 8, 1, 52, 52
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Língua Portuguesa') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 53, 53
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Língua Inglesa') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 54, 54
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Arte') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 2, 1, 55, 55
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Educação Física') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 7, 1, 56, 56
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Matemática') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 3, 1, 57, 57
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Biologia') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 3, 1, 58, 58
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Física') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 3, 1, 59, 59
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Química') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 2, 1, 60, 60
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('História') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 2, 1, 61, 61
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Geografia') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 62, 62
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Filosofia') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 63, 63
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Sociologia') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 64, 64
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Socioemocional') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 65, 65
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Língua Espanhola') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 66, 66
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Cidadania e Sustentabilidade') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 67, 67
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Fazendo as Contas Para o Futuro') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

INSERT INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
SELECT mx.id, mt.id, 1, 1, 68, 68
FROM matrizes_curriculares mx
JOIN materias mt ON mt.id = (SELECT mt2.id FROM materias mt2 WHERE LOWER(TRIM(mt2.nome)) = LOWER('Marketing Digital') ORDER BY mt2.id LIMIT 1)
WHERE mx.nome = 'Ribeirânia 2026 - 2ª Série'
  AND NOT EXISTS (
    SELECT 1 FROM matrizes_curriculares_componentes x
    WHERE x.matriz_id = mx.id AND x.materia_id = mt.id
  );

-- 8) Salas e ambientes
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'ANF-7-ESQUERDO-1', 'Anf.7-Esquerdo', 'outro', 24, NULL, NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'ANF-7-ESQUERDO-1'
     OR (sl.nome = 'Anf.7-Esquerdo' AND IFNULL(sl.bloco,'') = IFNULL(NULL,''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'TECNOLOGIAS-2', 'Tecnologias', 'laboratorio', 50, NULL, NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'TECNOLOGIAS-2'
     OR (sl.nome = 'Tecnologias' AND IFNULL(sl.bloco,'') = IFNULL(NULL,''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'EXPLORATORIO-FISICA-3', 'Exploratório Física', 'laboratorio', 50, NULL, NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'EXPLORATORIO-FISICA-3'
     OR (sl.nome = 'Exploratório Física' AND IFNULL(sl.bloco,'') = IFNULL(NULL,''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'QUIMICA-4', 'Quimica', 'laboratorio', 100, NULL, NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'QUIMICA-4'
     OR (sl.nome = 'Quimica' AND IFNULL(sl.bloco,'') = IFNULL(NULL,''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'A-SALA-1-A-5', 'Sala 1 A', 'sala', 20, 'A', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'A-SALA-1-A-5'
     OR (sl.nome = 'Sala 1 A' AND IFNULL(sl.bloco,'') = IFNULL('A',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'A-SALA-3-A-6', 'Sala 3 A', 'sala', 42, 'A', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'A-SALA-3-A-6'
     OR (sl.nome = 'Sala 3 A' AND IFNULL(sl.bloco,'') = IFNULL('A',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'A-SALA-4-A-7', 'Sala 4 A', 'sala', 42, 'A', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'A-SALA-4-A-7'
     OR (sl.nome = 'Sala 4 A' AND IFNULL(sl.bloco,'') = IFNULL('A',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'A-SALA-5-A-8', 'Sala 5 A', 'sala', 42, 'A', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'A-SALA-5-A-8'
     OR (sl.nome = 'Sala 5 A' AND IFNULL(sl.bloco,'') = IFNULL('A',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'A-SALA-6-A-9', 'Sala 6 A', 'sala', 42, 'A', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'A-SALA-6-A-9'
     OR (sl.nome = 'Sala 6 A' AND IFNULL(sl.bloco,'') = IFNULL('A',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'A-SALA-7-A-10', 'Sala 7 A', 'sala', 42, 'A', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'A-SALA-7-A-10'
     OR (sl.nome = 'Sala 7 A' AND IFNULL(sl.bloco,'') = IFNULL('A',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'A-SALA-8-A-11', 'Sala 8 A', 'sala', 49, 'A', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'A-SALA-8-A-11'
     OR (sl.nome = 'Sala 8 A' AND IFNULL(sl.bloco,'') = IFNULL('A',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'A-SALA-9-A-12', 'Sala 9 A', 'sala', 49, 'A', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'A-SALA-9-A-12'
     OR (sl.nome = 'Sala 9 A' AND IFNULL(sl.bloco,'') = IFNULL('A',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'A-ANFITEATRO-1-13', 'ANFITEATRO 1', 'outro', 90, 'A', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'A-ANFITEATRO-1-13'
     OR (sl.nome = 'ANFITEATRO 1' AND IFNULL(sl.bloco,'') = IFNULL('A',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'A-SALA-RV-14', 'Sala RV', 'laboratorio', 50, 'A', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'A-SALA-RV-14'
     OR (sl.nome = 'Sala RV' AND IFNULL(sl.bloco,'') = IFNULL('A',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'A-SALA-INTERATIVA-15', 'Sala Interativa', 'sala', 50, 'A', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'A-SALA-INTERATIVA-15'
     OR (sl.nome = 'Sala Interativa' AND IFNULL(sl.bloco,'') = IFNULL('A',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'B-ANF-1-DIREITO-16', 'ANF 1 - DIREITO', 'outro', 105, 'B', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'B-ANF-1-DIREITO-16'
     OR (sl.nome = 'ANF 1 - DIREITO' AND IFNULL(sl.bloco,'') = IFNULL('B',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'B-ANF-1-ESQUERDO-17', 'ANF 1 - ESQUERDO', 'outro', 81, 'B', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'B-ANF-1-ESQUERDO-17'
     OR (sl.nome = 'ANF 1 - ESQUERDO' AND IFNULL(sl.bloco,'') = IFNULL('B',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'B-SALAO-NOBRE-18', 'Salão Nobre', 'sala', 105, 'B', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'B-SALAO-NOBRE-18'
     OR (sl.nome = 'Salão Nobre' AND IFNULL(sl.bloco,'') = IFNULL('B',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'C-SALA-01-19', 'Sala 01', 'sala', 50, 'C', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'C-SALA-01-19'
     OR (sl.nome = 'Sala 01' AND IFNULL(sl.bloco,'') = IFNULL('C',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'C-SALA-02-20', 'Sala 02', 'sala', 50, 'C', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'C-SALA-02-20'
     OR (sl.nome = 'Sala 02' AND IFNULL(sl.bloco,'') = IFNULL('C',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'C-SALA-03-21', 'Sala 03', 'sala', 50, 'C', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'C-SALA-03-21'
     OR (sl.nome = 'Sala 03' AND IFNULL(sl.bloco,'') = IFNULL('C',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'C-SALA-04-22', 'Sala 04', 'sala', 50, 'C', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'C-SALA-04-22'
     OR (sl.nome = 'Sala 04' AND IFNULL(sl.bloco,'') = IFNULL('C',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'C-SALA-05-23', 'Sala 05', 'sala', 50, 'C', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'C-SALA-05-23'
     OR (sl.nome = 'Sala 05' AND IFNULL(sl.bloco,'') = IFNULL('C',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'C-SALA-06-24', 'Sala 06', 'sala', 50, 'C', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'C-SALA-06-24'
     OR (sl.nome = 'Sala 06' AND IFNULL(sl.bloco,'') = IFNULL('C',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'C-SALA-07-25', 'Sala 07', 'sala', 50, 'C', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'C-SALA-07-25'
     OR (sl.nome = 'Sala 07' AND IFNULL(sl.bloco,'') = IFNULL('C',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'C-SALA-08-26', 'Sala 08', 'sala', 50, 'C', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'C-SALA-08-26'
     OR (sl.nome = 'Sala 08' AND IFNULL(sl.bloco,'') = IFNULL('C',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'C-SALA-09-27', 'Sala 09', 'sala', 50, 'C', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'C-SALA-09-27'
     OR (sl.nome = 'Sala 09' AND IFNULL(sl.bloco,'') = IFNULL('C',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'D-ANFITEATRO-2-28', 'ANFITEATRO 2', 'outro', 90, 'D', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'D-ANFITEATRO-2-28'
     OR (sl.nome = 'ANFITEATRO 2' AND IFNULL(sl.bloco,'') = IFNULL('D',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'D-SALA-01-29', 'Sala 01', 'sala', 50, 'D', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'D-SALA-01-29'
     OR (sl.nome = 'Sala 01' AND IFNULL(sl.bloco,'') = IFNULL('D',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'D-SALA-02-30', 'Sala 02', 'sala', 50, 'D', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'D-SALA-02-30'
     OR (sl.nome = 'Sala 02' AND IFNULL(sl.bloco,'') = IFNULL('D',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'D-SALA-03-31', 'Sala 03', 'sala', 50, 'D', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'D-SALA-03-31'
     OR (sl.nome = 'Sala 03' AND IFNULL(sl.bloco,'') = IFNULL('D',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'D-SALA-04-32', 'Sala 04', 'sala', 50, 'D', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'D-SALA-04-32'
     OR (sl.nome = 'Sala 04' AND IFNULL(sl.bloco,'') = IFNULL('D',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'D-SALA-05-33', 'Sala 05', 'sala', 50, 'D', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'D-SALA-05-33'
     OR (sl.nome = 'Sala 05' AND IFNULL(sl.bloco,'') = IFNULL('D',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'D-SALA-06-34', 'Sala 06', 'sala', 50, 'D', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'D-SALA-06-34'
     OR (sl.nome = 'Sala 06' AND IFNULL(sl.bloco,'') = IFNULL('D',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'D-SALA-07-35', 'Sala 07', 'sala', 50, 'D', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'D-SALA-07-35'
     OR (sl.nome = 'Sala 07' AND IFNULL(sl.bloco,'') = IFNULL('D',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'D-SALA-08-36', 'Sala 08', 'sala', 50, 'D', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'D-SALA-08-36'
     OR (sl.nome = 'Sala 08' AND IFNULL(sl.bloco,'') = IFNULL('D',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'E-ANFITEATRO-3-37', 'ANFITEATRO-3', 'outro', 36, 'E', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'E-ANFITEATRO-3-37'
     OR (sl.nome = 'ANFITEATRO-3' AND IFNULL(sl.bloco,'') = IFNULL('E',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'E-SALA-01-38', 'Sala 01', 'sala', 50, 'E', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'E-SALA-01-38'
     OR (sl.nome = 'Sala 01' AND IFNULL(sl.bloco,'') = IFNULL('E',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'E-SALA-03-39', 'Sala 03', 'sala', 50, 'E', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'E-SALA-03-39'
     OR (sl.nome = 'Sala 03' AND IFNULL(sl.bloco,'') = IFNULL('E',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'E-SALA-04-40', 'Sala 04', 'sala', 50, 'E', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'E-SALA-04-40'
     OR (sl.nome = 'Sala 04' AND IFNULL(sl.bloco,'') = IFNULL('E',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'E-SALA-05-41', 'Sala 05', 'sala', 50, 'E', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'E-SALA-05-41'
     OR (sl.nome = 'Sala 05' AND IFNULL(sl.bloco,'') = IFNULL('E',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'E-SALA-06-42', 'Sala 06', 'sala', 50, 'E', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'E-SALA-06-42'
     OR (sl.nome = 'Sala 06' AND IFNULL(sl.bloco,'') = IFNULL('E',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'E-SALA-07-43', 'Sala 07', 'sala', 50, 'E', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'E-SALA-07-43'
     OR (sl.nome = 'Sala 07' AND IFNULL(sl.bloco,'') = IFNULL('E',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'E-SALA-08-44', 'Sala 08', 'sala', 50, 'E', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'E-SALA-08-44'
     OR (sl.nome = 'Sala 08' AND IFNULL(sl.bloco,'') = IFNULL('E',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'E-SALA-09-45', 'Sala 09', 'sala', 50, 'E', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'E-SALA-09-45'
     OR (sl.nome = 'Sala 09' AND IFNULL(sl.bloco,'') = IFNULL('E',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'E-ANFITEATRO-2-46', 'Anfiteatro 2', 'outro', 72, 'E', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'E-ANFITEATRO-2-46'
     OR (sl.nome = 'Anfiteatro 2' AND IFNULL(sl.bloco,'') = IFNULL('E',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'E-ANFITEATRO-3-47', 'Anfiteatro 3', 'outro', 70, 'E', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'E-ANFITEATRO-3-47'
     OR (sl.nome = 'Anfiteatro 3' AND IFNULL(sl.bloco,'') = IFNULL('E',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'G-8D-FRENTE-48', '8D- Frente', 'sala', 12, 'G', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'G-8D-FRENTE-48'
     OR (sl.nome = '8D- Frente' AND IFNULL(sl.bloco,'') = IFNULL('G',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'G-8D-DIREITA-49', '8D- Direita', 'sala', 16, 'G', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'G-8D-DIREITA-49'
     OR (sl.nome = '8D- Direita' AND IFNULL(sl.bloco,'') = IFNULL('G',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'G-8D-ESQUERDA-50', '8D- Esquerda', 'sala', 12, 'G', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'G-8D-ESQUERDA-50'
     OR (sl.nome = '8D- Esquerda' AND IFNULL(sl.bloco,'') = IFNULL('G',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'G-9D-FRENTE-51', '9D - Frente', 'sala', 12, 'G', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'G-9D-FRENTE-51'
     OR (sl.nome = '9D - Frente' AND IFNULL(sl.bloco,'') = IFNULL('G',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'G-9D-DIREITA-52', '9D - Direita', 'sala', 16, 'G', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'G-9D-DIREITA-52'
     OR (sl.nome = '9D - Direita' AND IFNULL(sl.bloco,'') = IFNULL('G',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'G-9D-ESQUERDA-53', '9D - Esquerda', 'sala', 12, 'G', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'G-9D-ESQUERDA-53'
     OR (sl.nome = '9D - Esquerda' AND IFNULL(sl.bloco,'') = IFNULL('G',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'I-SALA-DE-INGLES-1-54', 'Sala de Ingles 1', 'sala', 21, 'I', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'I-SALA-DE-INGLES-1-54'
     OR (sl.nome = 'Sala de Ingles 1' AND IFNULL(sl.bloco,'') = IFNULL('I',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'I-SALA-DE-INGLES-2-55', 'Sala de Ingles 2', 'sala', 20, 'I', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'I-SALA-DE-INGLES-2-55'
     OR (sl.nome = 'Sala de Ingles 2' AND IFNULL(sl.bloco,'') = IFNULL('I',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'I-SALA-DE-INGLES-3-56', 'Sala de Inglês 3', 'sala', 20, 'I', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'I-SALA-DE-INGLES-3-56'
     OR (sl.nome = 'Sala de Inglês 3' AND IFNULL(sl.bloco,'') = IFNULL('I',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'I-SALA-DE-INGLES-4-57', 'Sala de Inglês 4', 'sala', 20, 'I', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'I-SALA-DE-INGLES-4-57'
     OR (sl.nome = 'Sala de Inglês 4' AND IFNULL(sl.bloco,'') = IFNULL('I',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'I-SALA-DE-INGLES-5-58', 'Sala de inglês 5', 'sala', 20, 'I', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'I-SALA-DE-INGLES-5-58'
     OR (sl.nome = 'Sala de inglês 5' AND IFNULL(sl.bloco,'') = IFNULL('I',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'I-SALA-ESTUDO-INGLES-59', 'Sala estudo inglês', 'outro', 20, 'I', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'I-SALA-ESTUDO-INGLES-59'
     OR (sl.nome = 'Sala estudo inglês' AND IFNULL(sl.bloco,'') = IFNULL('I',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'I-SALA-DE-ESPANHOL-7-60', 'Sala de Espanhol 7', 'sala', 12, 'I', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'I-SALA-DE-ESPANHOL-7-60'
     OR (sl.nome = 'Sala de Espanhol 7' AND IFNULL(sl.bloco,'') = IFNULL('I',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'I-SALA-DE-ESPANHOL-8-61', 'Sala de Espanhol 8', 'sala', 12, 'I', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'I-SALA-DE-ESPANHOL-8-61'
     OR (sl.nome = 'Sala de Espanhol 8' AND IFNULL(sl.bloco,'') = IFNULL('I',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'J-SALA-DE-ESTUDO-62', 'Sala de Estudo', 'outro', 25, 'J', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'J-SALA-DE-ESTUDO-62'
     OR (sl.nome = 'Sala de Estudo' AND IFNULL(sl.bloco,'') = IFNULL('J',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'P-LAB-BIOLOGIA-63', 'Lab. Biologia', 'laboratorio', 50, 'P', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'P-LAB-BIOLOGIA-63'
     OR (sl.nome = 'Lab. Biologia' AND IFNULL(sl.bloco,'') = IFNULL('P',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'P-SALA-3D-64', 'Sala 3D', 'laboratorio', 50, 'P', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'P-SALA-3D-64'
     OR (sl.nome = 'Sala 3D' AND IFNULL(sl.bloco,'') = IFNULL('P',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT '2C', 'Sala 2 C', 'sala', 42, 'C', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = '2C'
     OR (sl.nome = 'Sala 2 C' AND IFNULL(sl.bloco,'') = IFNULL('C',''))
);
INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, ativo)
SELECT 'SALA-2', 'Sala 2', 'sala', 50, 'C', NULL, 1 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM school_locations sl
  WHERE sl.codigo = 'SALA-2'
     OR (sl.nome = 'Sala 2' AND IFNULL(sl.bloco,'') = IFNULL('C',''))
);

-- 9) Turmas
INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id,
  curso_novo_id, ativo, tipo_ensino, vagas, turno, sala_padrao_id, observacoes)
SELECT '5AM', 2026, al.id, '8º Ano', s.id, mx.id, c.id, 1, 'Ensino Fundamental II',
  NULL, 'Matutino', sl.id, NULL
FROM ano_letivo al
JOIN curso c ON c.nome = 'Fundamental II Regular'
JOIN serie s ON s.curso_id = c.id AND s.nome = '8º Ano'
JOIN matrizes_curriculares mx ON mx.nome = 'Ribeirânia 2026 - 8º Ano'
LEFT JOIN school_locations sl ON sl.id = (
  SELECT sl2.id FROM school_locations sl2 WHERE sl2.nome = 'Sala 5 A' ORDER BY sl2.id LIMIT 1
)
WHERE al.ano = 2026
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.nome = '5AM' AND t.ano_letivo = 2026);

INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id,
  curso_novo_id, ativo, tipo_ensino, vagas, turno, sala_padrao_id, observacoes)
SELECT '2CM', 2026, al.id, '9º Ano', s.id, mx.id, c.id, 1, 'Ensino Fundamental II',
  NULL, 'Matutino', sl.id, NULL
FROM ano_letivo al
JOIN curso c ON c.nome = 'Fundamental II Regular'
JOIN serie s ON s.curso_id = c.id AND s.nome = '9º Ano'
JOIN matrizes_curriculares mx ON mx.nome = 'Ribeirânia 2026 - 9º Ano'
LEFT JOIN school_locations sl ON sl.id = (
  SELECT sl2.id FROM school_locations sl2 WHERE sl2.nome = 'Sala 2 C' ORDER BY sl2.id LIMIT 1
)
WHERE al.ano = 2026
  AND NOT EXISTS (SELECT 1 FROM turmas t WHERE t.nome = '2CM' AND t.ano_letivo = 2026);

-- 10) Professores
INSERT INTO professores (nome, email, senha_hash, password, codigo_prof, materias, turmas, ativo, pagante)
SELECT 'A definir', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', 'ADEFINIR', JSON_ARRAY(), JSON_ARRAY(), 1, 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM professores WHERE codigo_prof = 'ADEFINIR');

INSERT INTO professores (nome, email, senha_hash, password, codigo_prof, materias, turmas, ativo, pagante)
SELECT 'Igor Zapata da Silva', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', 'PROF-IGORZAPATADASILVA', JSON_ARRAY('Geografia'), JSON_ARRAY(), 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM professores WHERE nome = 'Igor Zapata da Silva');

-- Vínculo professor × turmas (JSON de IDs usado pelo painel do professor)
UPDATE professores p SET p.turmas = (
  SELECT JSON_ARRAYAGG(t.id) FROM turmas t WHERE t.nome IN ('2CM', '5AM') AND t.ano_letivo = 2026
) WHERE p.nome = 'Igor Zapata da Silva';

-- 11) Grade horária (pula horários sem componente; professor vazio → 'A definir')
INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 1, '07:10:00', '07:55:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Portuguesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 1
      AND g.horario_de = '07:10:00' AND g.horario_ate = '07:55:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 1, '07:55:00', '08:40:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Portuguesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 1
      AND g.horario_de = '07:55:00' AND g.horario_ate = '08:40:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 1, '09:00:00', '09:45:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Matemática') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 1
      AND g.horario_de = '09:00:00' AND g.horario_ate = '09:45:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 1, '09:45:00', '10:30:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Portuguesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 1
      AND g.horario_de = '09:45:00' AND g.horario_ate = '10:30:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 1, '10:50:00', '11:35:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Matemática') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 1
      AND g.horario_de = '10:50:00' AND g.horario_ate = '11:35:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 1, '11:35:00', '12:20:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Ciências') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 1
      AND g.horario_de = '11:35:00' AND g.horario_ate = '12:20:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 2, '07:10:00', '07:55:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('História') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 2
      AND g.horario_de = '07:10:00' AND g.horario_ate = '07:55:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 2, '07:55:00', '08:40:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Geografia') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.id = (SELECT p2.id FROM professores p2 WHERE p2.nome = 'Igor Zapata da Silva' ORDER BY p2.id LIMIT 1)
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 2
      AND g.horario_de = '07:55:00' AND g.horario_ate = '08:40:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 2, '09:00:00', '09:45:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Inglesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 2
      AND g.horario_de = '09:00:00' AND g.horario_ate = '09:45:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 2, '09:45:00', '10:30:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Matemática') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 2
      AND g.horario_de = '09:45:00' AND g.horario_ate = '10:30:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 2, '10:50:00', '11:35:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Socioemocional') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 2
      AND g.horario_de = '10:50:00' AND g.horario_ate = '11:35:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 2, '11:35:00', '12:20:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Matemática') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 2
      AND g.horario_de = '11:35:00' AND g.horario_ate = '12:20:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 3, '07:10:00', '07:55:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('História') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 3
      AND g.horario_de = '07:10:00' AND g.horario_ate = '07:55:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 3, '07:55:00', '08:40:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Ciências') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 3
      AND g.horario_de = '07:55:00' AND g.horario_ate = '08:40:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 3, '09:00:00', '09:45:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Portuguesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 3
      AND g.horario_de = '09:00:00' AND g.horario_ate = '09:45:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 3, '09:45:00', '10:30:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Portuguesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 3
      AND g.horario_de = '09:45:00' AND g.horario_ate = '10:30:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 3, '10:50:00', '11:35:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Ciências') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 3
      AND g.horario_de = '10:50:00' AND g.horario_ate = '11:35:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 3, '11:35:00', '12:20:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Educação Física') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 3
      AND g.horario_de = '11:35:00' AND g.horario_ate = '12:20:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 4, '09:00:00', '09:45:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Inglesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 4
      AND g.horario_de = '09:00:00' AND g.horario_ate = '09:45:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 4, '09:45:00', '10:30:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Ciências') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 4
      AND g.horario_de = '09:45:00' AND g.horario_ate = '10:30:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 4, '10:50:00', '11:35:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Matemática') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 4
      AND g.horario_de = '10:50:00' AND g.horario_ate = '11:35:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 4, '11:35:00', '12:20:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Inglesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 4
      AND g.horario_de = '11:35:00' AND g.horario_ate = '12:20:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 5, '07:10:00', '07:55:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('História') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 5
      AND g.horario_de = '07:10:00' AND g.horario_ate = '07:55:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 5, '07:55:00', '08:40:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Portuguesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 5
      AND g.horario_de = '07:55:00' AND g.horario_ate = '08:40:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 5, '09:00:00', '09:45:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Geografia') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.id = (SELECT p2.id FROM professores p2 WHERE p2.nome = 'Igor Zapata da Silva' ORDER BY p2.id LIMIT 1)
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 5
      AND g.horario_de = '09:00:00' AND g.horario_ate = '09:45:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 5, '09:45:00', '10:30:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Portuguesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 5
      AND g.horario_de = '09:45:00' AND g.horario_ate = '10:30:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 5, '10:50:00', '11:35:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Geografia') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.id = (SELECT p2.id FROM professores p2 WHERE p2.nome = 'Igor Zapata da Silva' ORDER BY p2.id LIMIT 1)
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 5
      AND g.horario_de = '10:50:00' AND g.horario_ate = '11:35:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 5, '11:35:00', '12:20:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Arte') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 5
      AND g.horario_de = '11:35:00' AND g.horario_ate = '12:20:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 1, '07:10:00', '07:55:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Inglesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 1
      AND g.horario_de = '07:10:00' AND g.horario_ate = '07:55:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 1, '07:55:00', '08:40:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Ciências') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 1
      AND g.horario_de = '07:55:00' AND g.horario_ate = '08:40:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 1, '09:00:00', '09:45:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Geografia') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.id = (SELECT p2.id FROM professores p2 WHERE p2.nome = 'Igor Zapata da Silva' ORDER BY p2.id LIMIT 1)
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 1
      AND g.horario_de = '09:00:00' AND g.horario_ate = '09:45:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 1, '09:45:00', '10:30:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Ciências') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 1
      AND g.horario_de = '09:45:00' AND g.horario_ate = '10:30:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 1, '10:50:00', '11:35:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Portuguesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 1
      AND g.horario_de = '10:50:00' AND g.horario_ate = '11:35:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 1, '11:35:00', '12:20:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Inglesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 1
      AND g.horario_de = '11:35:00' AND g.horario_ate = '12:20:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 2, '07:10:00', '07:55:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('História') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 2
      AND g.horario_de = '07:10:00' AND g.horario_ate = '07:55:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 2, '07:55:00', '08:40:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Socioemocional') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 2
      AND g.horario_de = '07:55:00' AND g.horario_ate = '08:40:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 2, '09:00:00', '09:45:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Matemática') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 2
      AND g.horario_de = '09:00:00' AND g.horario_ate = '09:45:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 2, '09:45:00', '10:30:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('História') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 2
      AND g.horario_de = '09:45:00' AND g.horario_ate = '10:30:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 2, '10:50:00', '11:35:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Portuguesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 2
      AND g.horario_de = '10:50:00' AND g.horario_ate = '11:35:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 2, '11:35:00', '12:20:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Portuguesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 2
      AND g.horario_de = '11:35:00' AND g.horario_ate = '12:20:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 3, '07:10:00', '07:55:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('História') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 3
      AND g.horario_de = '07:10:00' AND g.horario_ate = '07:55:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 3, '07:55:00', '08:40:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Geografia') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.id = (SELECT p2.id FROM professores p2 WHERE p2.nome = 'Igor Zapata da Silva' ORDER BY p2.id LIMIT 1)
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 3
      AND g.horario_de = '07:55:00' AND g.horario_ate = '08:40:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 3, '09:00:00', '09:45:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Geografia') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.id = (SELECT p2.id FROM professores p2 WHERE p2.nome = 'Igor Zapata da Silva' ORDER BY p2.id LIMIT 1)
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 3
      AND g.horario_de = '09:00:00' AND g.horario_ate = '09:45:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 3, '09:45:00', '10:30:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Portuguesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 3
      AND g.horario_de = '09:45:00' AND g.horario_ate = '10:30:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 3, '10:50:00', '11:35:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Matemática') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 3
      AND g.horario_de = '10:50:00' AND g.horario_ate = '11:35:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 3, '11:35:00', '12:20:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Portuguesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 3
      AND g.horario_de = '11:35:00' AND g.horario_ate = '12:20:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 4, '09:00:00', '09:45:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Arte') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 4
      AND g.horario_de = '09:00:00' AND g.horario_ate = '09:45:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 4, '09:45:00', '10:30:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Matemática') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 4
      AND g.horario_de = '09:45:00' AND g.horario_ate = '10:30:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 4, '10:50:00', '11:35:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Portuguesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 4
      AND g.horario_de = '10:50:00' AND g.horario_ate = '11:35:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 4, '11:35:00', '12:20:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Matemática') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 4
      AND g.horario_de = '11:35:00' AND g.horario_ate = '12:20:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 5, '07:10:00', '07:55:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Ciências') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 5
      AND g.horario_de = '07:10:00' AND g.horario_ate = '07:55:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 5, '07:55:00', '08:40:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Inglesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 5
      AND g.horario_de = '07:55:00' AND g.horario_ate = '08:40:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 5, '09:00:00', '09:45:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Ciências') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 5
      AND g.horario_de = '09:00:00' AND g.horario_ate = '09:45:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 5, '09:45:00', '10:30:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Matemática') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 5
      AND g.horario_de = '09:45:00' AND g.horario_ate = '10:30:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 5, '10:50:00', '11:35:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Ciências') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 5
      AND g.horario_de = '10:50:00' AND g.horario_ate = '11:35:00'
      AND g.materia_id = m.id
  );

INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
SELECT 5, '11:35:00', '12:20:00', t.id, p.id, m.id, 'manha'
FROM turmas t
JOIN materias m ON m.id = (SELECT m2.id FROM materias m2 WHERE LOWER(TRIM(m2.nome)) = LOWER('Língua Inglesa') ORDER BY m2.id LIMIT 1)
JOIN professores p ON p.codigo_prof = 'ADEFINIR'
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (
    SELECT 1 FROM grade_horaria g
    WHERE g.turma_id = t.id AND g.dia_semana = 5
      AND g.horario_de = '11:35:00' AND g.horario_ate = '12:20:00'
      AND g.materia_id = m.id
  );

-- 12) Alunos (RA = número da matrícula da planilha 11)
SET @unidade_id := (SELECT MIN(id) FROM unidades);
INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'João Victor Peres Semensato Barboni', 'a111995', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '111995', '111995', t.id, @unidade_id,
  '8º Ano', '2013-01-25', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '111995' OR a.nome = 'João Victor Peres Semensato Barboni');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Kallyane Carvalho Leme', 'a112661', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '112661', '112661', t.id, @unidade_id,
  '8º Ano', '2013-04-08', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '112661' OR a.nome = 'Kallyane Carvalho Leme');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Martina Patelli de Oliveira Randi', 'a112557', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '112557', '112557', t.id, @unidade_id,
  '8º Ano', '2013-05-04', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '112557' OR a.nome = 'Martina Patelli de Oliveira Randi');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Théo Grespan Zuccolotto Motta Sousa', 'a90699', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '90699', '90699', t.id, @unidade_id,
  '8º Ano', '2012-12-27', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '90699' OR a.nome = 'Théo Grespan Zuccolotto Motta Sousa');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Beatriz Barga Silva Ansanello', 'a91995', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '91995', '91995', t.id, @unidade_id,
  '8º Ano', '2012-09-22', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '91995' OR a.nome = 'Beatriz Barga Silva Ansanello');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Letícia Veiga do Amaral Mello', 'a90135', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '90135', '90135', t.id, @unidade_id,
  '8º Ano', '2012-11-19', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '90135' OR a.nome = 'Letícia Veiga do Amaral Mello');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Alice Kuroishi Correa', 'a90705', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '90705', '90705', t.id, @unidade_id,
  '8º Ano', '2013-05-27', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '90705' OR a.nome = 'Alice Kuroishi Correa');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Marina Motta Camargo', 'a90537', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '90537', '90537', t.id, @unidade_id,
  '8º Ano', '2012-10-02', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '90537' OR a.nome = 'Marina Motta Camargo');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Hellena Marostica Nocera', 'a110716', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '110716', '110716', t.id, @unidade_id,
  '8º Ano', '2012-09-30', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '110716' OR a.nome = 'Hellena Marostica Nocera');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Heitor Daziano Corsi', 'a92795', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '92795', '92795', t.id, @unidade_id,
  '8º Ano', '2012-07-09', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '92795' OR a.nome = 'Heitor Daziano Corsi');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Clara Paes Leme Evangelista', 'a92489', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '92489', '92489', t.id, @unidade_id,
  '8º Ano', '2013-05-25', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '92489' OR a.nome = 'Clara Paes Leme Evangelista');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Breno Achiles Merlin', 'a110476', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '110476', '110476', t.id, @unidade_id,
  '8º Ano', '2012-11-05', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '110476' OR a.nome = 'Breno Achiles Merlin');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Júlia Scarparo Moraes', 'a109607', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '109607', '109607', t.id, @unidade_id,
  '8º Ano', '2012-10-29', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '109607' OR a.nome = 'Júlia Scarparo Moraes');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Carolina Bragantine de Melo', 'a97708', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '97708', '97708', t.id, @unidade_id,
  '8º Ano', '2012-10-21', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '97708' OR a.nome = 'Carolina Bragantine de Melo');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Pedro Escoura Noccioli', 'a95761', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '95761', '95761', t.id, @unidade_id,
  '8º Ano', '2012-06-26', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '95761' OR a.nome = 'Pedro Escoura Noccioli');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Davi Tiem Novo', 'a90084', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '90084', '90084', t.id, @unidade_id,
  '8º Ano', '2012-10-15', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '90084' OR a.nome = 'Davi Tiem Novo');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Lara Cocenas', 'a97561', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '97561', '97561', t.id, @unidade_id,
  '8º Ano', '2013-05-14', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '97561' OR a.nome = 'Lara Cocenas');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Maria Clara Aranda Aguiar', 'a92075', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '92075', '92075', t.id, @unidade_id,
  '8º Ano', '2012-08-21', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '92075' OR a.nome = 'Maria Clara Aranda Aguiar');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Sofia Zacharias Scandiuzzi', 'a92068', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '92068', '92068', t.id, @unidade_id,
  '8º Ano', '2013-02-14', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '92068' OR a.nome = 'Sofia Zacharias Scandiuzzi');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Alice Silveira de Oliveira', 'a96067', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '96067', '96067', t.id, @unidade_id,
  '8º Ano', '2012-11-14', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '96067' OR a.nome = 'Alice Silveira de Oliveira');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Lucas Ruffing Pereira', 'a91114', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '91114', '91114', t.id, @unidade_id,
  '8º Ano', '2012-10-16', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '91114' OR a.nome = 'Lucas Ruffing Pereira');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Manuela Carneiro De Castro', 'a110633', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '110633', '110633', t.id, @unidade_id,
  '8º Ano', '2013-03-05', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '110633' OR a.nome = 'Manuela Carneiro De Castro');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Daniel Ascencio Darini', 'a111762', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '111762', '111762', t.id, @unidade_id,
  '8º Ano', '2012-12-08', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '111762' OR a.nome = 'Daniel Ascencio Darini');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Isabella Santinho Benedeti', 'a112048', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '112048', '112048', t.id, @unidade_id,
  '8º Ano', '2012-07-07', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '112048' OR a.nome = 'Isabella Santinho Benedeti');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Lucca Jeronimo Dimas', 'a90324', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '90324', '90324', t.id, @unidade_id,
  '8º Ano', '2013-05-28', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '90324' OR a.nome = 'Lucca Jeronimo Dimas');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Manuela Diniz Coupê', 'a113031', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '113031', '113031', t.id, @unidade_id,
  '8º Ano', '2013-03-16', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '113031' OR a.nome = 'Manuela Diniz Coupê');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Giovana Shimokomaki Romano', 'a76413', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '76413', '76413', t.id, @unidade_id,
  '8º Ano', '2013-05-28', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '76413' OR a.nome = 'Giovana Shimokomaki Romano');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Elleonora Ferreira Pagnanelli', 'a98089', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '98089', '98089', t.id, @unidade_id,
  '8º Ano', '2012-11-01', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '98089' OR a.nome = 'Elleonora Ferreira Pagnanelli');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Julia Ribeiro De Sales Cabral', 'a113178', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '113178', '113178', t.id, @unidade_id,
  '8º Ano', '2012-10-23', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '113178' OR a.nome = 'Julia Ribeiro De Sales Cabral');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Sophia Dalri Nishimura', 'a78595', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '78595', '78595', t.id, @unidade_id,
  '8º Ano', '2013-04-10', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '78595' OR a.nome = 'Sophia Dalri Nishimura');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Eduarda Dias Da Silva', 'a110667', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '110667', '110667', t.id, @unidade_id,
  '8º Ano', '2013-01-09', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '110667' OR a.nome = 'Eduarda Dias Da Silva');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Manuella Corsini Almeida Ramassi', 'a78001', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '78001', '78001', t.id, @unidade_id,
  '8º Ano', '2012-12-25', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '78001' OR a.nome = 'Manuella Corsini Almeida Ramassi');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Maitê Batista Azambuja', 'a110730', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '110730', '110730', t.id, @unidade_id,
  '8º Ano', '2012-09-04', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '110730' OR a.nome = 'Maitê Batista Azambuja');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Gabriela Camargo Borges', 'a113489', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '113489', '113489', t.id, @unidade_id,
  '8º Ano', '2013-04-23', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '113489' OR a.nome = 'Gabriela Camargo Borges');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Lucas Duarte Faria', 'a112642', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '112642', '112642', t.id, @unidade_id,
  '9º Ano', '2011-10-20', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '112642' OR a.nome = 'Lucas Duarte Faria');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Alice Carneiro De Marco', 'a112666', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '112666', '112666', t.id, @unidade_id,
  '9º Ano', '2012-02-16', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '112666' OR a.nome = 'Alice Carneiro De Marco');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Ana Carolina Rozante de Paula', 'a109785', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '109785', '109785', t.id, @unidade_id,
  '9º Ano', '2012-02-14', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '109785' OR a.nome = 'Ana Carolina Rozante de Paula');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Vittoria Oliveira Brinck', 'a73381', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '73381', '73381', t.id, @unidade_id,
  '9º Ano', '2012-05-29', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '73381' OR a.nome = 'Vittoria Oliveira Brinck');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'César Pavan Mendes', 'a90081', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '90081', '90081', t.id, @unidade_id,
  '9º Ano', '2012-03-30', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '90081' OR a.nome = 'César Pavan Mendes');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Teresa Masson e Soares', 'a111780', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '111780', '111780', t.id, @unidade_id,
  '9º Ano', '2011-05-02', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '111780' OR a.nome = 'Teresa Masson e Soares');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Maria Júlia Cassiaro Betteti', 'a110700', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '110700', '110700', t.id, @unidade_id,
  '9º Ano', '2012-06-16', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '110700' OR a.nome = 'Maria Júlia Cassiaro Betteti');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Amanda Cocenas Junqueira', 'a97566', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '97566', '97566', t.id, @unidade_id,
  '9º Ano', '2011-09-10', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '97566' OR a.nome = 'Amanda Cocenas Junqueira');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Betina Esteves Palucci', 'a109852', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '109852', '109852', t.id, @unidade_id,
  '9º Ano', '2012-05-19', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '109852' OR a.nome = 'Betina Esteves Palucci');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Gabriella Solly Cropanise Toledo', 'a78449', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '78449', '78449', t.id, @unidade_id,
  '9º Ano', '2011-09-13', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '78449' OR a.nome = 'Gabriella Solly Cropanise Toledo');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Luara Maniezi Zafalon', 'a75974', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '75974', '75974', t.id, @unidade_id,
  '9º Ano', '2011-12-05', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '75974' OR a.nome = 'Luara Maniezi Zafalon');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Caetano Rocinholi Aleixo', 'a99704', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '99704', '99704', t.id, @unidade_id,
  '9º Ano', '2011-07-21', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '99704' OR a.nome = 'Caetano Rocinholi Aleixo');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Isabela Mantovani Maghine', 'a90119', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '90119', '90119', t.id, @unidade_id,
  '9º Ano', '2012-02-20', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '90119' OR a.nome = 'Isabela Mantovani Maghine');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Gabriel Sant''Anna Vargas', 'a96051', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '96051', '96051', t.id, @unidade_id,
  '9º Ano', '2011-12-22', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '96051' OR a.nome = 'Gabriel Sant''Anna Vargas');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Lara Fernandes de Lima Tavares', 'a77748', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '77748', '77748', t.id, @unidade_id,
  '9º Ano', '2011-08-19', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '77748' OR a.nome = 'Lara Fernandes de Lima Tavares');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Isabela Berruezo Brucoglieri', 'a111821', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '111821', '111821', t.id, @unidade_id,
  '9º Ano', '2011-10-20', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '111821' OR a.nome = 'Isabela Berruezo Brucoglieri');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Julia Pereira Lima Sircilli', 'a111784', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '111784', '111784', t.id, @unidade_id,
  '9º Ano', '2011-10-18', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '111784' OR a.nome = 'Julia Pereira Lima Sircilli');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Larissa De Oliveira Rosa', 'a111806', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '111806', '111806', t.id, @unidade_id,
  '9º Ano', '2012-05-27', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '111806' OR a.nome = 'Larissa De Oliveira Rosa');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Alícia Fernandes Lima', 'a111999', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '111999', '111999', t.id, @unidade_id,
  '9º Ano', '2012-06-27', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '111999' OR a.nome = 'Alícia Fernandes Lima');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Anita Messenberg Marques', 'a112315', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '112315', '112315', t.id, @unidade_id,
  '9º Ano', '2011-09-01', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '112315' OR a.nome = 'Anita Messenberg Marques');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Enzo Hirasawa Cardoso', 'a111805', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '111805', '111805', t.id, @unidade_id,
  '9º Ano', '2012-01-31', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '111805' OR a.nome = 'Enzo Hirasawa Cardoso');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Maria Branco Mattei', 'a77771', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '77771', '77771', t.id, @unidade_id,
  '9º Ano', '2011-10-05', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '77771' OR a.nome = 'Maria Branco Mattei');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Guilherme Colucci Perone', 'a96061', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '96061', '96061', t.id, @unidade_id,
  '9º Ano', '2012-03-15', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '96061' OR a.nome = 'Guilherme Colucci Perone');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Thales Petian Bono', 'a111094', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '111094', '111094', t.id, @unidade_id,
  '9º Ano', '2011-07-21', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '111094' OR a.nome = 'Thales Petian Bono');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Pietro Petian Bono', 'a111107', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '111107', '111107', t.id, @unidade_id,
  '9º Ano', '2011-07-21', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '111107' OR a.nome = 'Pietro Petian Bono');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Olívia Menezes Caldeira De Oliveira', 'a113029', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '113029', '113029', t.id, @unidade_id,
  '9º Ano', '2011-10-07', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '113029' OR a.nome = 'Olívia Menezes Caldeira De Oliveira');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Ana Laura de Oliveira', 'a96059', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '96059', '96059', t.id, @unidade_id,
  '9º Ano', '2010-12-06', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '96059' OR a.nome = 'Ana Laura de Oliveira');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Leonardo Pscheidt', 'a96427', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '96427', '96427', t.id, @unidade_id,
  '9º Ano', '2011-04-26', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '96427' OR a.nome = 'Leonardo Pscheidt');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Henri Moreira Ferreira', 'a99766', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '99766', '99766', t.id, @unidade_id,
  '9º Ano', '2011-07-14', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '99766' OR a.nome = 'Henri Moreira Ferreira');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Nicolas André Gallacio', 'a76415', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '76415', '76415', t.id, @unidade_id,
  '9º Ano', '2011-10-04', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '76415' OR a.nome = 'Nicolas André Gallacio');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Maria Clara Santos Soares', 'a112586', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '112586', '112586', t.id, @unidade_id,
  '9º Ano', '2012-03-12', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '112586' OR a.nome = 'Maria Clara Santos Soares');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Laís Leandro Tavares', 'a92595', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '92595', '92595', t.id, @unidade_id,
  '9º Ano', '2012-03-30', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '92595' OR a.nome = 'Laís Leandro Tavares');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Sofia Sanches Gomes', 'a113083', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '113083', '113083', t.id, @unidade_id,
  '9º Ano', '2011-09-14', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '113083' OR a.nome = 'Sofia Sanches Gomes');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'José Guilherme Corrêa Gonçalves', 'a74529', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '74529', '74529', t.id, @unidade_id,
  '9º Ano', '2012-02-15', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '74529' OR a.nome = 'José Guilherme Corrêa Gonçalves');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'João Vitor Nogueira Bolsoni', 'a113111', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '113111', '113111', t.id, @unidade_id,
  '9º Ano', '2011-01-10', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '113111' OR a.nome = 'João Vitor Nogueira Bolsoni');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Juliah de Ponti Almeida', 'a97609', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '97609', '97609', t.id, @unidade_id,
  '9º Ano', '2012-05-01', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '97609' OR a.nome = 'Juliah de Ponti Almeida');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Maria Valentina Vital Naves', 'a112134', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '112134', '112134', t.id, @unidade_id,
  '9º Ano', '2012-03-06', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '112134' OR a.nome = 'Maria Valentina Vital Naves');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Melissa Luciana Nascimento da Silva', 'a109258', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '109258', '109258', t.id, @unidade_id,
  '9º Ano', '2011-11-08', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '109258' OR a.nome = 'Melissa Luciana Nascimento da Silva');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Rafael Augusto Perna Dos Santos', 'a110574', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '110574', '110574', t.id, @unidade_id,
  '9º Ano', '2011-02-15', 'M', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '110574' OR a.nome = 'Rafael Augusto Perna Dos Santos');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Gabriela Passalha Duarte Gomiero', 'a113496', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '113496', '113496', t.id, @unidade_id,
  '9º Ano', '2011-08-22', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '113496' OR a.nome = 'Gabriela Passalha Duarte Gomiero');

INSERT INTO alunos (nome, nickname, email, senha_hash, password, ra, codigo_aluno, turma_id, unidade_id,
  serie, data_nasc, sexo, nacionalidade, ativo, status, pagante, primeiro_acesso)
SELECT 'Mila Santos Silva', 'a113504', NULL, '$2y$12$cn8j9xCrBWl1F0fjzN0GM.F4htG2eYICiXj9SN76UvZCG3sZ3eQYG', '', '113504', '113504', t.id, @unidade_id,
  '9º Ano', '2010-04-17', 'F', 'Brasileira', 1, 'ACTIVE', 1, 1
FROM turmas t
WHERE t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
  AND NOT EXISTS (SELECT 1 FROM alunos a WHERE a.ra = '113504' OR a.nome = 'Mila Santos Silva');

-- 13) Matrículas (aluno ↔ turma 2026)
INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '111995'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '112661'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '112557'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '90699'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '91995'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '90135'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '90705'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '90537'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '110716'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '92795'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '92489'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '110476'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '109607'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '97708'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '95761'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '90084'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '97561'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '92075'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '92068'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '96067'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '91114'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '110633'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '111762'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '112048'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '90324'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '113031'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '76413'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '98089'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '113178'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '78595'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '110667'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '78001'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '110730'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '5AM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '113489'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '112642'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '112666'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '109785'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '73381'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '90081'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '111780'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '110700'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '97566'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '109852'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '78449'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '75974'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '99704'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '90119'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '96051'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '77748'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '111821'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '111784'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '111806'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '111999'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '112315'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '111805'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '77771'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '96061'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '111094'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '111107'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '113029'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '96059'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '96427'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '99766'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '76415'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '112586'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '92595'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '113083'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '74529'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '113111'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '97609'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '112134'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '109258'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '110574'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '113496'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
SELECT a.id, t.id, al.id, '2026-01-01', 'ativa'
FROM alunos a
JOIN turmas t ON t.id = (SELECT t2.id FROM turmas t2 WHERE t2.nome = '2CM' AND t2.ano_letivo = 2026 ORDER BY t2.id LIMIT 1)
JOIN ano_letivo al ON al.ano = 2026
WHERE a.ra = '113504'
  AND NOT EXISTS (
    SELECT 1 FROM matricula m
    WHERE m.aluno_id = a.id AND m.turma_id = t.id AND m.ano_letivo_id = al.id
  );

-- Conferência
SELECT 'ano_letivo' AS tabela, COUNT(*) AS qtd FROM ano_letivo
UNION ALL SELECT 'curso', COUNT(*) FROM curso
UNION ALL SELECT 'serie', COUNT(*) FROM serie
UNION ALL SELECT 'materias', COUNT(*) FROM materias
UNION ALL SELECT 'matrizes_curriculares', COUNT(*) FROM matrizes_curriculares
UNION ALL SELECT 'matrizes_curriculares_componentes', COUNT(*) FROM matrizes_curriculares_componentes
UNION ALL SELECT 'school_locations', COUNT(*) FROM school_locations
UNION ALL SELECT 'turmas', COUNT(*) FROM turmas
UNION ALL SELECT 'professores', COUNT(*) FROM professores
UNION ALL SELECT 'grade_horaria', COUNT(*) FROM grade_horaria
UNION ALL SELECT 'alunos', COUNT(*) FROM alunos
UNION ALL SELECT 'matricula', COUNT(*) FROM matricula;

