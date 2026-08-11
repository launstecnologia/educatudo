-- ============================================================
-- SEED DEMO — Histórico Escolar oficial
-- Execute APENAS no tenant "demo" / escola teste via painel Master
-- Não rodar em massa em produção.
-- Idempotente: hash fixo do documento demo.
-- Pré-requisito: 2026_07_15_historico_escolar_oficial.sql
-- Tolerante a schema incompleto (verifica colunas/tabelas antes de usar).
-- ============================================================

SET @db := DATABASE();
SET @hash_demo := 'demodemo0123456789abcdef0123456789abcdef0123456789abcdef01234567';
SET @email_demo := 'historico.demo@teste.educatudo.local';

SET @tem_unidades := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades'
);
SET @tem_hist := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'historico_documentos'
);
SET @col_codigo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'codigo_aluno'
);
SET @col_nacionalidade := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'nacionalidade'
);
SET @col_nome_mae := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'nome_mae'
);
SET @col_cpf := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'cpf'
);
SET @col_unidade_id := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'unidade_id'
);
SET @col_ra := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'ra'
);
SET @col_ato := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'ato_autorizacao'
);

-- 1) Unidade matriz (dados básicos; atos legais só se a coluna existir)
SET @sql_unidades := IF(@tem_unidades = 0, 'SELECT 1',
  IF(@col_ato = 0,
"UPDATE unidades SET
  razao_social = COALESCE(NULLIF(TRIM(razao_social), ''), 'Escola Teste EducaTudo Ltda'),
  cnpj = COALESCE(NULLIF(TRIM(cnpj), ''), '12.345.678/0001-90'),
  inep = COALESCE(NULLIF(TRIM(inep), ''), '35123456'),
  endereco = COALESCE(NULLIF(TRIM(endereco), ''), 'Rua das Palmeiras'),
  numero = COALESCE(NULLIF(TRIM(numero), ''), '100'),
  bairro = COALESCE(NULLIF(TRIM(bairro), ''), 'Centro'),
  cidade = COALESCE(NULLIF(TRIM(cidade), ''), 'São Paulo'),
  uf = COALESCE(NULLIF(TRIM(uf), ''), 'SP'),
  cep = COALESCE(NULLIF(TRIM(cep), ''), '01001-000'),
  diretor_nome = COALESCE(NULLIF(TRIM(diretor_nome), ''), 'Maria Diretora Silva'),
  secretario_nome = COALESCE(NULLIF(TRIM(secretario_nome), ''), 'João Secretário Santos')
WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM unidades WHERE ativo = 1) t)",
"UPDATE unidades SET
  razao_social = COALESCE(NULLIF(TRIM(razao_social), ''), 'Escola Teste EducaTudo Ltda'),
  cnpj = COALESCE(NULLIF(TRIM(cnpj), ''), '12.345.678/0001-90'),
  inep = COALESCE(NULLIF(TRIM(inep), ''), '35123456'),
  endereco = COALESCE(NULLIF(TRIM(endereco), ''), 'Rua das Palmeiras'),
  numero = COALESCE(NULLIF(TRIM(numero), ''), '100'),
  bairro = COALESCE(NULLIF(TRIM(bairro), ''), 'Centro'),
  cidade = COALESCE(NULLIF(TRIM(cidade), ''), 'São Paulo'),
  uf = COALESCE(NULLIF(TRIM(uf), ''), 'SP'),
  cep = COALESCE(NULLIF(TRIM(cep), ''), '01001-000'),
  diretor_nome = COALESCE(NULLIF(TRIM(diretor_nome), ''), 'Maria Diretora Silva'),
  secretario_nome = COALESCE(NULLIF(TRIM(secretario_nome), ''), 'João Secretário Santos'),
  ato_autorizacao = COALESCE(NULLIF(TRIM(ato_autorizacao), ''), 'Portaria SEE nº 1234/2015, DOE 20/03/2015'),
  ato_credenciamento = COALESCE(NULLIF(TRIM(ato_credenciamento), ''), 'Resolução CEE nº 56/2016'),
  ato_reconhecimento = COALESCE(NULLIF(TRIM(ato_reconhecimento), ''), 'Parecer CEE nº 210/2017, DOE 12/08/2017'),
  diretor_registro = COALESCE(NULLIF(TRIM(diretor_registro), ''), 'RG-DIR-4521'),
  secretario_registro = COALESCE(NULLIF(TRIM(secretario_registro), ''), 'RG-SEC-8832')
WHERE id = (SELECT id FROM (SELECT MIN(id) AS id FROM unidades WHERE ativo = 1) t)")
);
PREPARE s FROM @sql_unidades; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) Aluno demo — INSERT mínimo (+ ra/codigo/unidade quando existirem)
SET @sql_aluno_ins := CONCAT(
  'INSERT INTO alunos (nome, email, senha_hash, password, serie, data_nasc, ativo, pagante',
  IF(@col_ra > 0, ', ra', ''),
  IF(@col_codigo > 0, ', codigo_aluno', ''),
  IF(@col_unidade_id > 0 AND @tem_unidades > 0, ', unidade_id', ''),
  ') SELECT ',
  '''Aluno Demo Histórico Escolar'', ',
  '''', @email_demo, ''', ',
  '''$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'', ',
  '''Demo@2026'', ',
  '''9º Ano'', ',
  '''2010-05-15'', ',
  '1, 0',
  IF(@col_ra > 0, ', ''HISTDEMO01''', ''),
  IF(@col_codigo > 0, ', ''HISTDEMO01''', ''),
  IF(@col_unidade_id > 0 AND @tem_unidades > 0, ', (SELECT MIN(id) FROM unidades WHERE ativo = 1)', ''),
  ' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM alunos WHERE email = ''', @email_demo, ''' LIMIT 1)',
  IF(@col_codigo > 0, ' AND NOT EXISTS (SELECT 1 FROM alunos WHERE codigo_aluno = ''HISTDEMO01'' LIMIT 1)', ''),
  IF(@col_ra > 0 AND @col_codigo = 0, ' AND NOT EXISTS (SELECT 1 FROM alunos WHERE ra = ''HISTDEMO01'' LIMIT 1)', '')
);
PREPARE s FROM @sql_aluno_ins; EXECUTE s; DEALLOCATE PREPARE s;

-- UPDATE data_nasc do aluno demo (sempre redefine — evita comparar zero-date em sql_mode strict)
SET @sql_upd_base := CONCAT(
  'UPDATE alunos SET data_nasc = ''2010-05-15'' WHERE email = ''', @email_demo, ''''
);
PREPARE s FROM @sql_upd_base; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql_upd_civ := IF(@col_nacionalidade = 0, 'SELECT 1',
  CONCAT(
    'UPDATE alunos SET ',
    'nacionalidade = COALESCE(NULLIF(TRIM(nacionalidade), ''''), ''Brasileira''), ',
    'naturalidade = COALESCE(NULLIF(TRIM(naturalidade), ''''), ''São Paulo''), ',
    'uf_nascimento = COALESCE(NULLIF(TRIM(uf_nascimento), ''''), ''SP'') ',
    IF(@col_cpf > 0, ', cpf = COALESCE(NULLIF(TRIM(cpf), ''''), ''529.982.247-25''), rg = COALESCE(NULLIF(TRIM(rg), ''''), ''12.345.678-9'')', ''),
    ' WHERE email = ''', @email_demo, ''''
  )
);
PREPARE s FROM @sql_upd_civ; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql_upd_fil := IF(@col_nome_mae = 0, 'SELECT 1',
  CONCAT(
    'UPDATE alunos SET ',
    'nome_mae = COALESCE(NULLIF(TRIM(nome_mae), ''''), ''Ana Souza Oliveira''), ',
    'nome_pai = COALESCE(NULLIF(TRIM(nome_pai), ''''), ''Carlos Oliveira'') ',
    'WHERE email = ''', @email_demo, ''''
  )
);
PREPARE s FROM @sql_upd_fil; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql_upd_uni := IF(@col_unidade_id = 0 OR @tem_unidades = 0, 'SELECT 1',
  CONCAT(
    'UPDATE alunos SET unidade_id = COALESCE(unidade_id, (SELECT MIN(id) FROM unidades WHERE ativo = 1)) ',
    'WHERE email = ''', @email_demo, ''''
  )
);
PREPARE s FROM @sql_upd_uni; EXECUTE s; DEALLOCATE PREPARE s;

SET @aluno_id := (
  SELECT id FROM alunos WHERE email = @email_demo LIMIT 1
);

-- Resolve unidade_id só via SQL dinâmico (IF() avalia o SELECT da tabela e falha se não existir)
SET @unidade_id := NULL;
SET @sql_uid := IF(@tem_unidades = 0, 'SELECT NULL INTO @unidade_id',
  'SELECT MIN(id) INTO @unidade_id FROM unidades WHERE ativo = 1'
);
PREPARE s FROM @sql_uid; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) Documento demo
SET @col_sed := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'historico_documentos' AND COLUMN_NAME = 'numero_registro_sed'
);
SET @sql_doc := IF(@tem_hist = 0 OR @aluno_id IS NULL, 'SELECT 1',
  CONCAT(
    'INSERT INTO historico_documentos ',
    '(aluno_id, unidade_id, versao, status, hash_validacao, finalidade, observacoes_gerais',
    IF(@col_sed > 0, ', numero_registro_sed', ''),
    ', snapshot_json, emitido_em, emitido_por, conferido_em, created_at) ',
    'SELECT ',
    @aluno_id, ', ',
    IFNULL(@unidade_id, 'NULL'), ', ',
    '1, ''Assinado'', ''', @hash_demo, ''', ''Transferencia'', ',
    '''Documento de demonstração. Escala numérica de notas de 0 (zero) a 10 (dez).'', ',
    IF(@col_sed > 0, '''SEED-SED-DEMO-001'', ', ''),
    'NULL, NOW(), NULL, NOW(), NOW() FROM DUAL ',
    'WHERE NOT EXISTS (SELECT 1 FROM historico_documentos WHERE hash_validacao = ''', @hash_demo, ''')'
  )
);
PREPARE s FROM @sql_doc; EXECUTE s; DEALLOCATE PREPARE s;

SET @hist_id := (
  SELECT id FROM historico_documentos WHERE hash_validacao = @hash_demo LIMIT 1
);

-- 4) Itens / resultados / assinaturas (DELETE justificado: re-seed do pacote demo)
DELETE FROM historico_itens WHERE historico_id = @hist_id AND @hist_id IS NOT NULL;
DELETE FROM historico_resultados_anuais WHERE historico_id = @hist_id AND @hist_id IS NOT NULL;
DELETE FROM historico_assinaturas WHERE historico_id = @hist_id AND @hist_id IS NOT NULL;

INSERT INTO historico_itens
  (historico_id, ano_letivo, serie_ano, componente, resultado_valor, carga_horaria, frequencia_percentual, origem, escola_origem, ordem)
SELECT v.historico_id, v.ano_letivo, v.serie_ano, v.componente, v.resultado_valor, v.carga_horaria, v.frequencia_percentual, v.origem, v.escola_origem, v.ordem
FROM (
  SELECT @hist_id AS historico_id, '2023' AS ano_letivo, '7º Ano' AS serie_ano, 'Língua Portuguesa' AS componente, '8.0' AS resultado_valor, 160 AS carga_horaria, 96.50 AS frequencia_percentual, 'Externo' AS origem, 'EMEF Jardim das Flores' AS escola_origem, 1 AS ordem
  UNION ALL SELECT @hist_id, '2023', '7º Ano', 'Matemática', '7.5', 160, 95.00, 'Externo', 'EMEF Jardim das Flores', 2
  UNION ALL SELECT @hist_id, '2023', '7º Ano', 'História', '8.5', 80, 97.00, 'Externo', 'EMEF Jardim das Flores', 3
  UNION ALL SELECT @hist_id, '2023', '7º Ano', 'Geografia', '8.0', 80, 96.00, 'Externo', 'EMEF Jardim das Flores', 4
  UNION ALL SELECT @hist_id, '2023', '7º Ano', 'Ciências', '7.0', 120, 94.50, 'Externo', 'EMEF Jardim das Flores', 5
  UNION ALL SELECT @hist_id, '2024', '8º Ano', 'Língua Portuguesa', '8.2', 160, 97.20, 'Interno', NULL, 10
  UNION ALL SELECT @hist_id, '2024', '8º Ano', 'Matemática', '7.8', 160, 96.80, 'Interno', NULL, 11
  UNION ALL SELECT @hist_id, '2024', '8º Ano', 'História', '9.0', 80, 98.00, 'Interno', NULL, 12
  UNION ALL SELECT @hist_id, '2024', '8º Ano', 'Geografia', '8.5', 80, 97.50, 'Interno', NULL, 13
  UNION ALL SELECT @hist_id, '2024', '8º Ano', 'Ciências', '8.0', 120, 95.00, 'Interno', NULL, 14
  UNION ALL SELECT @hist_id, '2025', '9º Ano', 'Língua Portuguesa', '8.5', 160, 98.00, 'Interno', NULL, 20
  UNION ALL SELECT @hist_id, '2025', '9º Ano', 'Matemática', '7.0', 160, 94.00, 'Interno', NULL, 21
  UNION ALL SELECT @hist_id, '2025', '9º Ano', 'História', '8.8', 80, 97.00, 'Interno', NULL, 22
  UNION ALL SELECT @hist_id, '2025', '9º Ano', 'Geografia', '8.2', 80, 96.50, 'Interno', NULL, 23
  UNION ALL SELECT @hist_id, '2025', '9º Ano', 'Ciências', '7.5', 120, 95.50, 'Interno', NULL, 24
) v
WHERE @hist_id IS NOT NULL;

INSERT INTO historico_resultados_anuais
  (historico_id, ano_letivo, serie_ano, resultado, observacao)
SELECT v.historico_id, v.ano_letivo, v.serie_ano, v.resultado, v.observacao
FROM (
  SELECT @hist_id AS historico_id, '2023' AS ano_letivo, '7º Ano' AS serie_ano, 'Aprovado' AS resultado, 'Transferência recebida — histórico da EMEF Jardim das Flores' AS observacao
  UNION ALL SELECT @hist_id, '2024', '8º Ano', 'Aprovado', NULL
  UNION ALL SELECT @hist_id, '2025', '9º Ano', 'Aprovado_Conselho', 'Aprovado pelo Conselho de Classe em Matemática'
) v
WHERE @hist_id IS NOT NULL;

INSERT INTO historico_assinaturas
  (historico_id, usuario_id, usuario_nome, cargo, numero_registro, tipo, ip_origem, assinado_em)
SELECT v.historico_id, v.usuario_id, v.usuario_nome, v.cargo, v.numero_registro, v.tipo, v.ip_origem, NOW()
FROM (
  SELECT @hist_id AS historico_id, 1 AS usuario_id, 'João Secretário Santos' AS usuario_nome, 'Secretario_Escolar' AS cargo, 'RG-SEC-8832' AS numero_registro, 'Eletronica_Simples' AS tipo, '127.0.0.1' AS ip_origem
  UNION ALL SELECT @hist_id, 1, 'Maria Diretora Silva', 'Diretor', 'RG-DIR-4521', 'Eletronica_Simples', '127.0.0.1'
) v
WHERE @hist_id IS NOT NULL;

-- 5) Snapshot mínimo (sem depender de colunas opcionais de alunos/unidades)
UPDATE historico_documentos h
INNER JOIN alunos a ON a.id = h.aluno_id
SET h.snapshot_json = JSON_OBJECT(
  'aluno', JSON_OBJECT(
    'id', a.id,
    'nome', a.nome,
    'data_nasc', a.data_nasc,
    'email', a.email,
    'serie', a.serie
  ),
  'unidade', JSON_OBJECT(
    'id', h.unidade_id,
    'nome', 'Escola Teste EducaTudo Ltda',
    'razao_social', 'Escola Teste EducaTudo Ltda',
    'cnpj', '12.345.678/0001-90',
    'inep', '35123456',
    'endereco', 'Rua das Palmeiras',
    'numero', '100',
    'bairro', 'Centro',
    'cidade', 'São Paulo',
    'uf', 'SP',
    'cep', '01001-000',
    'diretor_nome', 'Maria Diretora Silva',
    'secretario_nome', 'João Secretário Santos',
    'ato_autorizacao', 'Portaria SEE nº 1234/2015, DOE 20/03/2015',
    'ato_credenciamento', 'Resolução CEE nº 56/2016',
    'ato_reconhecimento', 'Parecer CEE nº 210/2017, DOE 12/08/2017',
    'diretor_registro', 'RG-DIR-4521',
    'secretario_registro', 'RG-SEC-8832'
  ),
  'finalidade', h.finalidade,
  'versao', h.versao,
  'observacoes_gerais', h.observacoes_gerais,
  'itens', (
    SELECT JSON_ARRAYAGG(JSON_OBJECT(
      'ano_letivo', i.ano_letivo,
      'serie_ano', i.serie_ano,
      'componente', i.componente,
      'resultado_valor', i.resultado_valor,
      'carga_horaria', i.carga_horaria,
      'frequencia_percentual', i.frequencia_percentual,
      'origem', i.origem,
      'escola_origem', i.escola_origem
    ))
    FROM historico_itens i WHERE i.historico_id = h.id
  ),
  'resultados', (
    SELECT JSON_ARRAYAGG(JSON_OBJECT(
      'ano_letivo', r.ano_letivo,
      'serie_ano', r.serie_ano,
      'resultado', r.resultado,
      'observacao', r.observacao
    ))
    FROM historico_resultados_anuais r WHERE r.historico_id = h.id
  )
)
WHERE h.hash_validacao = @hash_demo
  AND @hist_id IS NOT NULL;
