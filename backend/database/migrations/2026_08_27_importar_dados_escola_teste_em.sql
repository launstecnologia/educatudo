-- =============================================================================
-- Seed Escola Educa Teste — Ensino Médio 2026 (tenant vazio).
-- Senha padrão (alunos, pais, professores, equipe): Teste@123
-- Alunos/professores: pagante = 0
--
-- Master: /master/migrations → a escola NOVA → Escolher → marcar SÓ este arquivo.
-- Não use "Executar todas" (importar_dados_* é pulado no bootstrap).
-- Rode antes as migrations de schema dessa escola.
-- Volume alto (diário B1+B2). Se o Master estourar 180s, rode este arquivo
-- com mysql no banco do tenant.
--
-- Já inclui: diário B1+B2, frequência (falta/atraso/justificada/saída),
-- entrada/saída (facial + secretaria), faltas agregadas, planos de aula
-- vinculados, eventos de prova/trabalho/participação no diário do dia.
--
-- Aborta se já existir aluno que não seja deste seed (nickname et.*).
-- Idempotente na reexecução do próprio seed.
-- Rollback: 2026_08_27_importar_dados_escola_teste_em_rollback.sql
-- =============================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '-03:00';

SET @et_outros := (
  SELECT COUNT(*) FROM alunos
  WHERE nickname IS NOT NULL AND nickname NOT LIKE 'et.%'
);
SET @et_guard := IF(
  @et_outros = 0,
  'SELECT 1',
  'SELECT * FROM `ERRO_seed_somente_escola_vazia_ou_reexecucao_et`'
);
PREPARE et_guard FROM @et_guard;
EXECUTE et_guard;
DEALLOCATE PREPARE et_guard;

DROP PROCEDURE IF EXISTS seed_escola_teste_em;

DELIMITER $$

CREATE PROCEDURE seed_escola_teste_em()
BEGIN
  DECLARE v_hash VARCHAR(255) DEFAULT '$2y$10$7BYAIlOgLu03H4QGEYTDn.VR01w.LYWq6Z/gNjBIMn528kciOFbOa';
  DECLARE v_admin INT DEFAULT 0;
  DECLARE v_unidade INT DEFAULT 0;
  DECLARE v_ano_id INT DEFAULT 0;
  DECLARE v_curso INT DEFAULT 0;
  DECLARE v_cal INT DEFAULT 0;
  DECLARE v_s1 INT DEFAULT 0;
  DECLARE v_s2 INT DEFAULT 0;
  DECLARE v_s3 INT DEFAULT 0;
  DECLARE v_mx1 INT DEFAULT 0;
  DECLARE v_mx2 INT DEFAULT 0;
  DECLARE v_mx3 INT DEFAULT 0;
  DECLARE v_tipo_prova INT DEFAULT 0;
  DECLARE v_tipo_trab INT DEFAULT 0;
  DECLARE v_tipo_part INT DEFAULT 0;
  DECLARE v_t INT DEFAULT 0;
  DECLARE v_m INT DEFAULT 0;
  DECLARE v_turma_id INT DEFAULT 0;
  DECLARE v_mat_id INT DEFAULT 0;
  DECLARE v_prof_id INT DEFAULT 0;
  DECLARE v_need INT DEFAULT 0;
  DECLARE v_placed INT DEFAULT 0;
  DECLARE v_try INT DEFAULT 0;
  DECLARE v_idx INT DEFAULT 0;
  DECLARE v_dia INT DEFAULT 0;
  DECLARE v_per INT DEFAULT 0;
  DECLARE v_slot INT DEFAULT 0;
  DECLARE v_aluno_id INT DEFAULT 0;
  DECLARE v_mae_id INT DEFAULT 0;
  DECLARE v_pai_id INT DEFAULT 0;
  DECLARE v_nick VARCHAR(50);
  DECLARE v_nome VARCHAR(255);
  DECLARE v_nome_mae VARCHAR(255);
  DECLARE v_nome_pai VARCHAR(255);
  DECLARE v_serie_nome VARCHAR(50);
  DECLARE v_serie_cod CHAR(1);
  DECLARE v_letra CHAR(1);
  DECLARE v_turno VARCHAR(10);
  DECLARE v_de TIME;
  DECLARE v_ate TIME;
  DECLARE v_ocup INT DEFAULT 0;
  DECLARE v_bloco_id INT DEFAULT 0;
  DECLARE v_bp INT DEFAULT 0;
  DECLARE v_bim INT DEFAULT 0;
  DECLARE v_ev INT DEFAULT 0;
  DECLARE v_titulo VARCHAR(255);
  DECLARE v_data_ev DATE;
  DECLARE v_hora1 TIME;
  DECLARE v_hora2 TIME;
  DECLARE v_regra INT DEFAULT 0;

  -- ── Equipe ────────────────────────────────────────────────
  INSERT INTO usuarios (tipo, perfil_admin, nome, email, senha_hash, ativo)
  SELECT 'admin_escola', 'dev', 'Admin Escola Educa Teste', 'admin@educateste.local', v_hash, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'admin@educateste.local');
  INSERT INTO usuarios (tipo, perfil_admin, nome, email, senha_hash, ativo)
  SELECT 'admin_escola', 'diretor', 'Helena Duarte', 'diretor@educateste.local', v_hash, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'diretor@educateste.local');
  INSERT INTO usuarios (tipo, perfil_admin, nome, email, senha_hash, ativo)
  SELECT 'admin_escola', 'coordenador', 'Camila Ribeiro', 'coordenador@educateste.local', v_hash, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'coordenador@educateste.local');
  INSERT INTO usuarios (tipo, perfil_admin, nome, email, senha_hash, ativo)
  SELECT 'admin_escola', 'secretaria', 'Renata Alves', 'secretaria@educateste.local', v_hash, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'secretaria@educateste.local');
  INSERT INTO usuarios (tipo, perfil_admin, nome, email, senha_hash, ativo)
  SELECT 'admin_escola', 'financeiro', 'Paulo Moreira', 'financeiro@educateste.local', v_hash, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'financeiro@educateste.local');
  SELECT id INTO v_admin FROM usuarios WHERE email = 'admin@educateste.local' LIMIT 1;

  INSERT INTO config_layout (config_key, config_value, config_type)
  VALUES ('system_title', 'Escola Educa Teste', 'text')
  ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);
  INSERT INTO config_layout (config_key, config_value, config_type)
  VALUES ('system_subtitle', 'Ensino Médio · 2026', 'text')
  ON DUPLICATE KEY UPDATE config_value = VALUES(config_value);

  -- ── Unidade ───────────────────────────────────────────────
  INSERT INTO unidades (
    nome, tipo, razao_social, cnpj, inep, dependencia_administrativa,
    endereco, numero, complemento, bairro, cidade, uf, cep, telefone, email,
    diretor_nome, secretario_nome, ato_autorizacao, ato_credenciamento, ato_reconhecimento,
    diretor_registro, secretario_registro, ativo
  )
  SELECT
    'Escola Educa Teste', 'matriz', 'Escola Educa Teste Ltda', '11.222.333/0001-81', '35987654', 'privada',
    'Rua das Palmeiras', '100', 'Bloco A', 'Centro', 'São Paulo', 'SP', '01310-100',
    '(11) 3000-2026', 'contato@educateste.local',
    'Helena Duarte', 'Renata Alves',
    'Portaria CEE/SP nº 2026/01', 'Parecer CEE/SP nº 2026/02', 'Resolução CEE/SP nº 2026/03',
    'RG 12.345.678-9 SSP/SP', 'RG 98.765.432-1 SSP/SP', 1
  FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM unidades WHERE cnpj = '11.222.333/0001-81' OR nome = 'Escola Educa Teste');
  SELECT id INTO v_unidade FROM unidades WHERE nome = 'Escola Educa Teste' LIMIT 1;

  -- ── Ano letivo ────────────────────────────────────────────
  INSERT INTO ano_letivo (ano, data_inicio, data_fim, ativo)
  SELECT 2026, '2026-02-02', '2026-12-15', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM ano_letivo WHERE ano = 2026);
  UPDATE ano_letivo SET data_inicio = '2026-02-02', data_fim = '2026-12-15', ativo = IF(ano = 2026, 1, 0);
  SELECT id INTO v_ano_id FROM ano_letivo WHERE ano = 2026 LIMIT 1;

  -- ── Calendário ────────────────────────────────────────────
  INSERT INTO calendario_letivo (ano, dias_meta, carga_horaria_meta, observacao)
  SELECT 2026, 200, 800, 'Calendário Escola Educa Teste' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM calendario_letivo WHERE ano = 2026);
  SELECT id INTO v_cal FROM calendario_letivo WHERE ano = 2026 LIMIT 1;

  INSERT INTO calendario_letivo_eventos (calendario_id, data_inicio, data_fim, tipo, descricao, visivel_aluno, visivel_professor, visivel_pais)
  SELECT v_cal, d.i, d.f, d.t, d.n, 1, 1, 1
  FROM (
    SELECT '2026-01-01' i, '2026-01-30' f, 'recesso' t, 'Férias / início do ano' n
    UNION ALL SELECT '2026-02-16', '2026-02-18', 'recesso', 'Carnaval'
    UNION ALL SELECT '2026-04-03', '2026-04-03', 'feriado', 'Paixão de Cristo'
    UNION ALL SELECT '2026-04-21', '2026-04-21', 'feriado', 'Tiradentes'
    UNION ALL SELECT '2026-05-01', '2026-05-01', 'feriado', 'Dia do Trabalho'
    UNION ALL SELECT '2026-06-04', '2026-06-04', 'feriado', 'Corpus Christi'
    UNION ALL SELECT '2026-07-13', '2026-07-24', 'recesso', 'Recesso de julho'
    UNION ALL SELECT '2026-09-07', '2026-09-07', 'feriado', 'Independência'
    UNION ALL SELECT '2026-10-12', '2026-10-12', 'feriado', 'Nossa Senhora Aparecida'
    UNION ALL SELECT '2026-11-02', '2026-11-02', 'feriado', 'Finados'
    UNION ALL SELECT '2026-11-20', '2026-11-20', 'feriado', 'Consciência Negra'
    UNION ALL SELECT '2026-12-16', '2026-12-31', 'recesso', 'Encerramento / férias'
    UNION ALL SELECT '2026-03-31', '2026-03-31', 'evento', 'Conselho de classe 1º bimestre'
    UNION ALL SELECT '2026-04-06', '2026-04-10', 'avaliacao', 'Semana de provas 1º bimestre'
    UNION ALL SELECT '2026-06-08', '2026-06-12', 'avaliacao', 'Semana de provas 2º bimestre'
    UNION ALL SELECT '2026-06-30', '2026-06-30', 'evento', 'Conselho de classe 2º bimestre'
    UNION ALL SELECT '2026-09-28', '2026-10-02', 'avaliacao', 'Semana de provas 3º bimestre'
    UNION ALL SELECT '2026-09-30', '2026-09-30', 'evento', 'Conselho de classe 3º bimestre'
    UNION ALL SELECT '2026-11-23', '2026-11-27', 'avaliacao', 'Semana de provas 4º bimestre'
    UNION ALL SELECT '2026-12-10', '2026-12-10', 'evento', 'Conselho de classe final'
  ) d
  WHERE NOT EXISTS (
    SELECT 1 FROM calendario_letivo_eventos e
    WHERE e.calendario_id = v_cal AND e.data_inicio = d.i AND e.descricao = d.n
  );

  -- ── Curso / séries ────────────────────────────────────────
  INSERT INTO curso (nome, tipo, possui_serie, descricao, ativo, ordem)
  SELECT 'Ensino Médio', 'regular', 1, 'Ensino Médio Regular — Escola Educa Teste', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM curso WHERE nome = 'Ensino Médio');
  SELECT id INTO v_curso FROM curso WHERE nome = 'Ensino Médio' LIMIT 1;

  INSERT INTO serie (curso_id, nome, ordem, ativo)
  SELECT v_curso, '1º Ano EM', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM serie WHERE curso_id = v_curso AND nome = '1º Ano EM');
  INSERT INTO serie (curso_id, nome, ordem, ativo)
  SELECT v_curso, '2º Ano EM', 2, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM serie WHERE curso_id = v_curso AND nome = '2º Ano EM');
  INSERT INTO serie (curso_id, nome, ordem, ativo)
  SELECT v_curso, '3º Ano EM', 3, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM serie WHERE curso_id = v_curso AND nome = '3º Ano EM');
  SELECT id INTO v_s1 FROM serie WHERE curso_id = v_curso AND nome = '1º Ano EM' LIMIT 1;
  SELECT id INTO v_s2 FROM serie WHERE curso_id = v_curso AND nome = '2º Ano EM' LIMIT 1;
  SELECT id INTO v_s3 FROM serie WHERE curso_id = v_curso AND nome = '3º Ano EM' LIMIT 1;

  IF (SELECT COUNT(*) FROM materias WHERE nome IN (
        'Língua Portuguesa','Matemática','Língua Inglesa','História','Geografia',
        'Física','Química','Biologia','Redação','Educação Física','Sociologia','Filosofia','Arte'
      ) AND ativo = 1) < 13 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Catálogo de componentes incompleto. Rode as migrations de schema antes.';
  END IF;

  -- ── Matrizes ──────────────────────────────────────────────
  INSERT INTO matrizes_curriculares (nome, codigo, curso_id, serie_id, modalidade, turno, carga_horaria_anual_prevista, dias_letivos_previstos, duracao_padrao_aula_minutos, base_legal, observacoes, ativo)
  SELECT 'Matriz 1º Ano EM — Educa Teste', 'ET-EM1', v_curso, v_s1, 'presencial', 'integral', 900, 200, 50, 'BNCC / LDB 9.394/96', '13 componentes', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM matrizes_curriculares WHERE codigo = 'ET-EM1');
  INSERT INTO matrizes_curriculares (nome, codigo, curso_id, serie_id, modalidade, turno, carga_horaria_anual_prevista, dias_letivos_previstos, duracao_padrao_aula_minutos, base_legal, observacoes, ativo)
  SELECT 'Matriz 2º Ano EM — Educa Teste', 'ET-EM2', v_curso, v_s2, 'presencial', 'integral', 900, 200, 50, 'BNCC / LDB 9.394/96', '13 componentes', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM matrizes_curriculares WHERE codigo = 'ET-EM2');
  INSERT INTO matrizes_curriculares (nome, codigo, curso_id, serie_id, modalidade, turno, carga_horaria_anual_prevista, dias_letivos_previstos, duracao_padrao_aula_minutos, base_legal, observacoes, ativo)
  SELECT 'Matriz 3º Ano EM — Educa Teste', 'ET-EM3', v_curso, v_s3, 'presencial', 'integral', 900, 200, 50, 'BNCC / LDB 9.394/96', '13 componentes', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM matrizes_curriculares WHERE codigo = 'ET-EM3');
  SELECT id INTO v_mx1 FROM matrizes_curriculares WHERE codigo = 'ET-EM1' LIMIT 1;
  SELECT id INTO v_mx2 FROM matrizes_curriculares WHERE codigo = 'ET-EM2' LIMIT 1;
  SELECT id INTO v_mx3 FROM matrizes_curriculares WHERE codigo = 'ET-EM3' LIMIT 1;

  INSERT IGNORE INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
  SELECT mx.id, m.id, c.aulas, 1, c.ord, c.ord
  FROM (
    SELECT 'Língua Portuguesa' n, 4 aulas, 1 ord UNION ALL SELECT 'Matemática', 4, 2
    UNION ALL SELECT 'Língua Inglesa', 2, 3 UNION ALL SELECT 'História', 2, 4
    UNION ALL SELECT 'Geografia', 2, 5 UNION ALL SELECT 'Física', 2, 6
    UNION ALL SELECT 'Química', 2, 7 UNION ALL SELECT 'Biologia', 2, 8
    UNION ALL SELECT 'Redação', 2, 9 UNION ALL SELECT 'Educação Física', 2, 10
    UNION ALL SELECT 'Sociologia', 1, 11 UNION ALL SELECT 'Filosofia', 1, 12
    UNION ALL SELECT 'Arte', 1, 13
  ) c
  INNER JOIN materias m ON m.nome = c.n
  INNER JOIN matrizes_curriculares mx ON mx.codigo IN ('ET-EM1','ET-EM2','ET-EM3');

  -- ── Salas ─────────────────────────────────────────────────
  SET v_t = 1;
  WHILE v_t <= 9 DO
    INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, responsavel_nome, ativo)
    SELECT CONCAT('ET-SALA-', LPAD(v_t, 2, '0')), CONCAT('Sala ', LPAD(v_t, 2, '0')), 'sala', 50,
           IF(v_t <= 5, 'A', 'B'), IF(v_t <= 5, '1', '2'), 'Coordenação', 1
    FROM DUAL
    WHERE NOT EXISTS (SELECT 1 FROM school_locations WHERE codigo = CONCAT('ET-SALA-', LPAD(v_t, 2, '0')));
    SET v_t = v_t + 1;
  END WHILE;

  -- ── Tipos de avaliação ────────────────────────────────────
  INSERT INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem, chave_quadro)
  SELECT 'Prova Bimestral', 'Avaliação principal do bimestre.', 1, 20, 'prova_bim' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM provas_tipos_avaliacao WHERE nome = 'Prova Bimestral' AND deleted_at IS NULL);
  INSERT INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem, chave_quadro)
  SELECT 'Trabalho', 'Trabalho bimestral.', 1, 30, 'trabalho' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM provas_tipos_avaliacao WHERE nome = 'Trabalho' AND deleted_at IS NULL);
  INSERT INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem, chave_quadro)
  SELECT 'Participação', 'Participação em aula.', 1, 40, 'participacao' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM provas_tipos_avaliacao WHERE nome = 'Participação' AND deleted_at IS NULL);
  SELECT id INTO v_tipo_prova FROM provas_tipos_avaliacao WHERE nome = 'Prova Bimestral' AND deleted_at IS NULL LIMIT 1;
  SELECT id INTO v_tipo_trab FROM provas_tipos_avaliacao WHERE nome = 'Trabalho' AND deleted_at IS NULL LIMIT 1;
  SELECT id INTO v_tipo_part FROM provas_tipos_avaliacao WHERE nome = 'Participação' AND deleted_at IS NULL LIMIT 1;

  INSERT INTO regras_academicas (nome, codigo, ano_letivo, curso_id, periodo_tipo, media_minima, frequencia_minima, usar_frequencia, recuperacao_tipo, recuperacao_composicao, round_mode, decimal_places, ativo)
  SELECT 'Regra EM Educa Teste', 'em-educa-teste', 2026, v_curso, 'bimestre', 6.00, 75.00, 1, 'periodo', 'maior_nota', 'none', 2, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM regras_academicas WHERE codigo = 'em-educa-teste');

  -- ── Professores (1 por matéria) ───────────────────────────
  INSERT INTO professores (nome, email, senha_hash, codigo_prof, materias, turmas, ativo, pagante, password)
  SELECT d.nome, d.email, v_hash, d.cod, JSON_ARRAY(d.mat), JSON_ARRAY(), 1, 0, ''
  FROM (
    SELECT 'Lúcia Prado' nome, 'portugues@educateste.local' email, 'ET-LPO' cod, 'Língua Portuguesa' mat
    UNION ALL SELECT 'Marcos Tavares', 'matematica@educateste.local', 'ET-MAT', 'Matemática'
    UNION ALL SELECT 'Helen Brooks', 'ingles@educateste.local', 'ET-ING', 'Língua Inglesa'
    UNION ALL SELECT 'Hugo Sampaio', 'historia@educateste.local', 'ET-HIS', 'História'
    UNION ALL SELECT 'Gisele Ramos', 'geografia@educateste.local', 'ET-GEO', 'Geografia'
    UNION ALL SELECT 'Fábio Nunes', 'fisica@educateste.local', 'ET-FIS', 'Física'
    UNION ALL SELECT 'Carla Menezes', 'quimica@educateste.local', 'ET-QUI', 'Química'
    UNION ALL SELECT 'Beatriz Cunha', 'biologia@educateste.local', 'ET-BIO', 'Biologia'
    UNION ALL SELECT 'Renata Oliveira', 'redacao@educateste.local', 'ET-RED', 'Redação'
    UNION ALL SELECT 'Érica Fontes', 'edfisica@educateste.local', 'ET-EDF', 'Educação Física'
    UNION ALL SELECT 'Sérgio Pacheco', 'sociologia@educateste.local', 'ET-SOC', 'Sociologia'
    UNION ALL SELECT 'Fernanda Dias', 'filosofia@educateste.local', 'ET-FIL', 'Filosofia'
    UNION ALL SELECT 'Amanda Vieira', 'arte@educateste.local', 'ET-ART', 'Arte'
  ) d
  WHERE NOT EXISTS (SELECT 1 FROM professores WHERE codigo_prof = d.cod);

  -- ── Turmas ────────────────────────────────────────────────
  INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id, curso_novo_id, ativo, tipo_ensino, vagas, turno, sala_padrao_id, observacoes)
  SELECT '1º A', 2026, v_ano_id, '1º Ano EM', v_s1, v_mx1, v_curso, 1, 'medio', 50, 'manha', (SELECT id FROM school_locations WHERE codigo = 'ET-SALA-01'), 'ET 1º A'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '1º A' AND ano_letivo = 2026);
  INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id, curso_novo_id, ativo, tipo_ensino, vagas, turno, sala_padrao_id, observacoes)
  SELECT '1º B', 2026, v_ano_id, '1º Ano EM', v_s1, v_mx1, v_curso, 1, 'medio', 50, 'manha', (SELECT id FROM school_locations WHERE codigo = 'ET-SALA-02'), 'ET 1º B'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '1º B' AND ano_letivo = 2026);
  INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id, curso_novo_id, ativo, tipo_ensino, vagas, turno, sala_padrao_id, observacoes)
  SELECT '1º C', 2026, v_ano_id, '1º Ano EM', v_s1, v_mx1, v_curso, 1, 'medio', 50, 'manha', (SELECT id FROM school_locations WHERE codigo = 'ET-SALA-03'), 'ET 1º C'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '1º C' AND ano_letivo = 2026);
  INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id, curso_novo_id, ativo, tipo_ensino, vagas, turno, sala_padrao_id, observacoes)
  SELECT '2º A', 2026, v_ano_id, '2º Ano EM', v_s2, v_mx2, v_curso, 1, 'medio', 50, 'manha', (SELECT id FROM school_locations WHERE codigo = 'ET-SALA-04'), 'ET 2º A'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '2º A' AND ano_letivo = 2026);
  INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id, curso_novo_id, ativo, tipo_ensino, vagas, turno, sala_padrao_id, observacoes)
  SELECT '2º B', 2026, v_ano_id, '2º Ano EM', v_s2, v_mx2, v_curso, 1, 'medio', 50, 'manha', (SELECT id FROM school_locations WHERE codigo = 'ET-SALA-05'), 'ET 2º B'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '2º B' AND ano_letivo = 2026);
  INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id, curso_novo_id, ativo, tipo_ensino, vagas, turno, sala_padrao_id, observacoes)
  SELECT '2º C', 2026, v_ano_id, '2º Ano EM', v_s2, v_mx2, v_curso, 1, 'medio', 50, 'tarde', (SELECT id FROM school_locations WHERE codigo = 'ET-SALA-06'), 'ET 2º C'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '2º C' AND ano_letivo = 2026);
  INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id, curso_novo_id, ativo, tipo_ensino, vagas, turno, sala_padrao_id, observacoes)
  SELECT '3º A', 2026, v_ano_id, '3º Ano EM', v_s3, v_mx3, v_curso, 1, 'medio', 50, 'tarde', (SELECT id FROM school_locations WHERE codigo = 'ET-SALA-07'), 'ET 3º A'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '3º A' AND ano_letivo = 2026);
  INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id, curso_novo_id, ativo, tipo_ensino, vagas, turno, sala_padrao_id, observacoes)
  SELECT '3º B', 2026, v_ano_id, '3º Ano EM', v_s3, v_mx3, v_curso, 1, 'medio', 50, 'tarde', (SELECT id FROM school_locations WHERE codigo = 'ET-SALA-08'), 'ET 3º B'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '3º B' AND ano_letivo = 2026);
  INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id, curso_novo_id, ativo, tipo_ensino, vagas, turno, sala_padrao_id, observacoes)
  SELECT '3º C', 2026, v_ano_id, '3º Ano EM', v_s3, v_mx3, v_curso, 1, 'medio', 50, 'tarde', (SELECT id FROM school_locations WHERE codigo = 'ET-SALA-09'), 'ET 3º C'
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM turmas WHERE nome = '3º C' AND ano_letivo = 2026);

  UPDATE professores SET turmas = (
    SELECT JSON_ARRAYAGG(id) FROM turmas WHERE ano_letivo = 2026 AND nome IN ('1º A','1º B','1º C','2º A','2º B','2º C','3º A','3º B','3º C')
  ) WHERE codigo_prof LIKE 'ET-%';

  -- ── Grade (sem choque: 1 professor/matéria) ───────────────
  DROP TEMPORARY TABLE IF EXISTS et_carga;
  CREATE TEMPORARY TABLE et_carga (ord INT PRIMARY KEY, nome VARCHAR(100), aulas INT);
  INSERT INTO et_carga VALUES
    (1,'Língua Portuguesa',4),(2,'Matemática',4),(3,'Língua Inglesa',2),(4,'História',2),
    (5,'Geografia',2),(6,'Física',2),(7,'Química',2),(8,'Biologia',2),(9,'Redação',2),
    (10,'Educação Física',2),(11,'Sociologia',1),(12,'Filosofia',1),(13,'Arte',1);

  DROP TEMPORARY TABLE IF EXISTS et_ocup_t;
  CREATE TEMPORARY TABLE et_ocup_t (turma_id INT, dia INT, per INT, PRIMARY KEY (turma_id, dia, per));
  DROP TEMPORARY TABLE IF EXISTS et_ocup_p;
  CREATE TEMPORARY TABLE et_ocup_p (mat_id INT, dia INT, per INT, turno VARCHAR(10), PRIMARY KEY (mat_id, dia, per, turno));

  SET v_t = 1;
  WHILE v_t <= 9 DO
    SET v_turno = IF(v_t <= 5, 'manha', 'tarde');
    SET v_turma_id = ELT(v_t,
      (SELECT id FROM turmas WHERE nome = '1º A' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '1º B' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '1º C' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '2º A' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '2º B' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '2º C' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '3º A' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '3º B' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '3º C' AND ano_letivo = 2026)
    );
    IF (SELECT COUNT(*) FROM grade_horaria WHERE turma_id = v_turma_id) = 0 THEN
      SET v_m = 1;
      WHILE v_m <= 13 DO
        SELECT aulas INTO v_need FROM et_carga WHERE ord = v_m;
        SELECT m.id, p.id INTO v_mat_id, v_prof_id
        FROM et_carga c
        INNER JOIN materias m ON m.nome = c.nome
        INNER JOIN professores p ON p.codigo_prof = CONCAT('ET-', ELT(v_m,'LPO','MAT','ING','HIS','GEO','FIS','QUI','BIO','RED','EDF','SOC','FIL','ART'))
        WHERE c.ord = v_m
        LIMIT 1;
        SET v_placed = 0;
        SET v_try = 0;
        WHILE v_try < 30 AND v_placed < v_need DO
          SET v_idx = (v_try + (v_t - 1) * 5 + (v_m - 1) * 3) MOD 30;
          SET v_dia = 1 + (v_idx DIV 6);
          SET v_per = v_idx MOD 6;
          SET v_ocup = 0;
          IF EXISTS (SELECT 1 FROM et_ocup_t WHERE turma_id = v_turma_id AND dia = v_dia AND per = v_per) THEN
            SET v_ocup = 1;
          END IF;
          IF v_ocup = 0 AND EXISTS (SELECT 1 FROM et_ocup_p WHERE mat_id = v_mat_id AND dia = v_dia AND per = v_per AND turno = v_turno) THEN
            SET v_ocup = 1;
          END IF;
          IF v_ocup = 0 THEN
            IF v_turno = 'manha' THEN
              SET v_de = ELT(v_per + 1, '07:30:00','08:20:00','09:30:00','10:20:00','11:10:00','12:00:00');
              SET v_ate = ELT(v_per + 1, '08:20:00','09:10:00','10:20:00','11:10:00','12:00:00','12:50:00');
            ELSE
              SET v_de = ELT(v_per + 1, '13:30:00','14:20:00','15:30:00','16:20:00','17:10:00','18:00:00');
              SET v_ate = ELT(v_per + 1, '14:20:00','15:10:00','16:20:00','17:10:00','18:00:00','18:50:00');
            END IF;
            INSERT INTO grade_horaria (turma_id, materia_id, professor_id, dia_semana, horario_de, horario_ate, periodo)
            VALUES (v_turma_id, v_mat_id, v_prof_id, v_dia, v_de, v_ate, v_turno);
            INSERT INTO et_ocup_t VALUES (v_turma_id, v_dia, v_per);
            INSERT INTO et_ocup_p VALUES (v_mat_id, v_dia, v_per, v_turno);
            SET v_placed = v_placed + 1;
          END IF;
          SET v_try = v_try + 1;
        END WHILE;
        IF v_placed < v_need THEN
          SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Grade horária não coube (choque de professor).';
        END IF;
        SET v_m = v_m + 1;
      END WHILE;
    END IF;
    SET v_t = v_t + 1;
  END WHILE;

  -- ── Alunos + pais + saúde ─────────────────────────────────
  SET v_t = 1;
  WHILE v_t <= 9 DO
    SET v_serie_cod = ELT(v_t, '1','1','1','2','2','2','3','3','3');
    SET v_letra = ELT(v_t, 'A','B','C','A','B','C','A','B','C');
    SET v_serie_nome = ELT(v_t, '1º Ano EM','1º Ano EM','1º Ano EM','2º Ano EM','2º Ano EM','2º Ano EM','3º Ano EM','3º Ano EM','3º Ano EM');
    SET v_turma_id = ELT(v_t,
      (SELECT id FROM turmas WHERE nome = '1º A' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '1º B' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '1º C' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '2º A' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '2º B' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '2º C' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '3º A' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '3º B' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '3º C' AND ano_letivo = 2026)
    );
    SET v_slot = 1;
    WHILE v_slot <= 50 DO
      SET v_nick = CONCAT('et.', v_serie_cod, LOWER(v_letra), LPAD(v_slot, 2, '0'));
      IF NOT EXISTS (SELECT 1 FROM alunos WHERE nickname = v_nick) THEN
        SET v_nome = CONCAT(
          ELT(1 + ((v_t * 50 + v_slot) MOD 12), 'Ana','Bruno','Carla','Diego','Elisa','Felipe','Gabriela','Henrique','Isabela','João','Karina','Lucas'),
          ' ',
          ELT(1 + ((v_slot * 3 + v_t) MOD 10), 'Almeida','Barbosa','Cardoso','Dias','Fernandes','Gomes','Lima','Oliveira','Silva','Souza')
        );
        SET v_nome_mae = CONCAT(ELT(1 + (v_slot MOD 8), 'Ana','Carla','Elisa','Helena','Marina','Olivia','Rafaela','Talita'), ' ', SUBSTRING_INDEX(v_nome, ' ', -1));
        SET v_nome_pai = IF(v_slot MOD 23 = 0, NULL, CONCAT(ELT(1 + (v_slot MOD 8), 'Bruno','Diego','Eduardo','Felipe','Henrique','João','Marcos','Pedro'), ' ', SUBSTRING_INDEX(v_nome, ' ', -1)));
        INSERT INTO alunos (
          nome, nickname, email, senha_hash, ra, codigo_aluno, turma_id, serie, unidade_id, data_nasc,
          ativo, pagante, status, password, primeiro_acesso, sexo, cpf, rg, telefone, celular, whatsapp,
          logradouro, numero, bairro, cidade, uf, cep, nome_mae, nome_pai, codigo_inep, nacionalidade,
          naturalidade, uf_nascimento, cor_raca, orgao_emissor, uf_rg, certidao_nascimento, certidao_livro,
          certidao_folha, certidao_termo, nis, zona, pais
        ) VALUES (
          v_nome, v_nick, CONCAT(v_nick, '@educateste.local'), v_hash,
          UPPER(REPLACE(v_nick, '.', '')), UPPER(REPLACE(v_nick, '.', '')),
          v_turma_id, v_serie_nome, v_unidade,
          DATE(CONCAT(2026 - 14 - CAST(v_serie_cod AS UNSIGNED), '-', LPAD(1 + ((v_slot - 1) MOD 12), 2, '0'), '-', LPAD(LEAST(28, v_slot), 2, '0'))),
          1, 0, 'ACTIVE', '', 0, IF(v_slot MOD 2 = 0, 'F', 'M'),
          LPAD(CAST(10000000000 + v_t * 1000 + v_slot AS CHAR), 11, '0'),
          CONCAT(LPAD(10 + (v_slot MOD 80), 2, '0'), '.', LPAD((v_slot * 3) MOD 1000, 3, '0'), '.', LPAD((v_slot * 7) MOD 1000, 3, '0'), '-', v_slot MOD 10),
          CONCAT('1130001', LPAD(v_slot, 3, '0')), CONCAT('119', LPAD(70000000 + v_t * 100 + v_slot, 8, '0')),
          CONCAT('119', LPAD(70000000 + v_t * 100 + v_slot, 8, '0')),
          ELT(1 + (v_slot MOD 6), 'Rua das Palmeiras', 'Avenida Paulista', 'Rua Augusta', 'Rua da Consolação', 'Rua Vergueiro', 'Rua Pamplona'),
          CAST(10 + v_slot AS CHAR), ELT(1 + (v_slot MOD 6), 'Centro', 'Bela Vista', 'Consolação', 'Jardins', 'Moema', 'Pinheiros'),
          'São Paulo', 'SP', CONCAT('01', LPAD(100 + v_slot, 3, '0'), LPAD(10 + v_slot, 3, '0')),
          v_nome_mae, v_nome_pai, CONCAT('3518', LPAD(v_t * 100 + v_slot, 8, '0')),
          'Brasileira', 'São Paulo', 'SP', ELT(1 + (v_slot MOD 4), 'Parda', 'Branca', 'Preta', 'Branca'),
          'SSP', 'SP', LPAD(CONCAT('12345672020', v_t, v_slot), 32, '0'),
          LPAD(v_slot, 3, '0'), LPAD(v_slot, 3, '0'), LPAD(v_t * 50 + v_slot, 5, '0'),
          LPAD(CAST(10000000000 + v_t * 1000 + v_slot AS CHAR), 11, '0'),
          IF(v_slot MOD 31 = 0, 'rural', 'urbana'), 'Brasil'
        );
        SET v_aluno_id = LAST_INSERT_ID();

        INSERT INTO responsaveis (nome, email, senha_hash, cpf, telefone, celular, rg, data_nascimento, endereco, numero, bairro, cidade, uf, cep, ativo, password)
        VALUES (
          v_nome_mae, CONCAT('mae.', v_nick, '@educateste.local'), v_hash,
          LPAD(CAST(20000000000 + v_t * 1000 + v_slot AS CHAR), 11, '0'),
          CONCAT('119', LPAD(71000000 + v_t * 100 + v_slot, 8, '0')),
          CONCAT('119', LPAD(71000000 + v_t * 100 + v_slot, 8, '0')),
          CONCAT('20.', LPAD(v_slot, 3, '0'), '.', LPAD(v_t, 3, '0'), '-1'),
          DATE(CONCAT(2026 - 42, '-04-', LPAD(LEAST(28, v_slot), 2, '0'))),
          'Rua das Palmeiras', CAST(10 + v_slot AS CHAR), 'Centro', 'São Paulo', 'SP', '01310-100', 1, ''
        );
        SET v_mae_id = LAST_INSERT_ID();
        INSERT INTO alunos_responsaveis (aluno_id, responsavel_id, tipo_vinculo, parentesco, is_financeiro, ativo, pode_retirar, recebe_boletos, recebe_boletim, recebe_notificacoes, responsavel_pedagogico, assina_documentos)
        VALUES (v_aluno_id, v_mae_id, 'mae', 'Mãe', 1, 1, 1, 1, 1, 1, 1, 1);
        UPDATE alunos SET responsavel_id = v_mae_id WHERE id = v_aluno_id;

        IF v_nome_pai IS NOT NULL THEN
          INSERT INTO responsaveis (nome, email, senha_hash, cpf, telefone, celular, rg, data_nascimento, endereco, numero, bairro, cidade, uf, cep, ativo, password)
          VALUES (
            v_nome_pai, CONCAT('pai.', v_nick, '@educateste.local'), v_hash,
            LPAD(CAST(30000000000 + v_t * 1000 + v_slot AS CHAR), 11, '0'),
            CONCAT('119', LPAD(72000000 + v_t * 100 + v_slot, 8, '0')),
            CONCAT('119', LPAD(72000000 + v_t * 100 + v_slot, 8, '0')),
            CONCAT('30.', LPAD(v_slot, 3, '0'), '.', LPAD(v_t, 3, '0'), '-2'),
            DATE(CONCAT(2026 - 44, '-08-', LPAD(LEAST(28, v_slot), 2, '0'))),
            'Rua das Palmeiras', CAST(10 + v_slot AS CHAR), 'Centro', 'São Paulo', 'SP', '01310-100', 1, ''
          );
          SET v_pai_id = LAST_INSERT_ID();
          INSERT INTO alunos_responsaveis (aluno_id, responsavel_id, tipo_vinculo, parentesco, is_financeiro, ativo, pode_retirar, recebe_boletos, recebe_boletim, recebe_notificacoes, responsavel_pedagogico, assina_documentos)
          VALUES (v_aluno_id, v_pai_id, 'pai', 'Pai', 0, 1, 1, 0, 1, 1, 0, 0);
        END IF;

        INSERT INTO alunos_ficha_complementar (
          aluno_id, tipo_sanguineo, plano_saude, plano_saude_numero, hospital_referencia,
          alergias, medicamentos_uso, condicoes_cronicas, deficiencias_obs,
          contato_emergencia_nome, contato_emergencia_telefone, contato_emergencia_parentesco,
          restricoes_alimentares, alimentacao_obs, usa_transporte_escolar, transporte_tipo,
          transporte_rota, transporte_ponto, transporte_responsavel, transporte_telefone
        ) VALUES (
          v_aluno_id,
          ELT(1 + (v_slot MOD 8), 'A+','O+','B+','A-','O+','O-','AB+','B+'),
          IF(v_slot MOD 3 = 0, NULL, ELT(1 + (v_slot MOD 4), 'Unimed','SulAmérica','Bradesco Saúde','Amil')),
          IF(v_slot MOD 3 = 0, NULL, CONCAT('ET', LPAD(v_t * 50 + v_slot, 8, '0'))),
          IF(v_slot MOD 4 = 0, 'Hospital das Clínicas', 'Santa Casa de São Paulo'),
          IF(v_slot MOD 11 = 0, 'Dipirona', NULL),
          IF(v_slot MOD 19 = 0, 'Bombinha de asma (salbutamol) se crise', NULL),
          IF(v_slot MOD 19 = 0, 'Asma leve', NULL),
          IF(v_slot MOD 41 = 0, 'Usa óculos para miopia', NULL),
          v_nome_mae, CONCAT('119', LPAD(71000000 + v_t * 100 + v_slot, 8, '0')), 'Mãe',
          IF(v_slot MOD 13 = 0, 'Intolerância à lactose', NULL),
          IF(v_slot MOD 13 = 0, 'Preferir merenda sem leite.', NULL),
          IF(v_slot MOD 17 = 0, 1, 0),
          IF(v_slot MOD 17 = 0, 'escolar', 'proprio'),
          IF(v_slot MOD 17 = 0, 'Linha EM', NULL),
          IF(v_slot MOD 17 = 0, 'Rua das Palmeiras', NULL),
          IF(v_slot MOD 17 = 0, 'Van Educa — Sr. Paulo', NULL),
          IF(v_slot MOD 17 = 0, '11960001000', NULL)
        );

        INSERT INTO alunos_documentos (aluno_id, tipo, titulo, status, observacao, entregue_em, created_by)
        VALUES
          (v_aluno_id, 'rg', 'RG', 'entregue', 'Seed teste', NOW(), v_admin),
          (v_aluno_id, 'cpf', 'CPF', 'entregue', 'Seed teste', NOW(), v_admin),
          (v_aluno_id, 'certidao_nascimento', 'Certidão de nascimento', 'entregue', 'Seed teste', NOW(), v_admin);
      ELSE
        SELECT id INTO v_aluno_id FROM alunos WHERE nickname = v_nick LIMIT 1;
        UPDATE alunos SET turma_id = v_turma_id, unidade_id = v_unidade, pagante = 0 WHERE id = v_aluno_id;
      END IF;

      INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
      SELECT v_aluno_id, v_turma_id, v_ano_id, '2026-02-02', 'ativa' FROM DUAL
      WHERE v_aluno_id > 0
        AND NOT EXISTS (
          SELECT 1 FROM matricula WHERE aluno_id = v_aluno_id AND turma_id = v_turma_id AND ano_letivo_id = v_ano_id
        );

      SET v_slot = v_slot + 1;
    END WHILE;
    SET v_t = v_t + 1;
  END WHILE;

  -- ── Diário B1+B2 (todos os dias letivos) ──────────────────
  INSERT INTO diario_aulas (
    grade_horaria_id, professor_id, turma_id, materia_id, data_aula, horario_de, horario_ate,
    execucao, conteudo_realizado, observacoes, tipo_aula, status, finalizada_at
  )
  SELECT gh.id, gh.professor_id, gh.turma_id, gh.materia_id, d.data_aula, gh.horario_de, gh.horario_ate,
         CASE
           WHEN (CRC32(CONCAT(gh.id, '|', d.data_aula, '|ex')) % 100) < 10 THEN 'parcial'
           WHEN (CRC32(CONCAT(gh.id, '|', d.data_aula, '|ex')) % 100) < 15 THEN 'alterado'
           ELSE 'conforme_planejado'
         END,
         CONCAT('ET ', mat.nome, ' — ',
           CASE
             WHEN (CRC32(CONCAT(gh.id, '|', d.data_aula, '|ex')) % 100) < 10
               THEN 'conteúdo iniciado; restante na próxima aula.'
             WHEN (CRC32(CONCAT(gh.id, '|', d.data_aula, '|ex')) % 100) < 15
               THEN 'sequência ajustada em sala (atividade extra).'
             ELSE CONCAT('aula ministrada conforme o plano (', t.nome, ').')
           END
         ),
         CONCAT('ET-SEED|',
           CASE
             WHEN (CRC32(CONCAT(gh.id, '|', d.data_aula, '|ex')) % 100) < 10 THEN 'parcial'
             WHEN (CRC32(CONCAT(gh.id, '|', d.data_aula, '|ex')) % 100) < 15 THEN 'alterado'
             ELSE 'ok'
           END
         ),
         'regular', 'finalizada', NOW()
  FROM grade_horaria gh
  INNER JOIN turmas t ON t.id = gh.turma_id AND t.ano_letivo = 2026
  INNER JOIN materias mat ON mat.id = gh.materia_id
  INNER JOIN (
    SELECT DATE('2026-02-02') + INTERVAL seq DAY AS data_aula
    FROM (
      SELECT a.n + b.n * 10 + c.n * 100 AS seq
      FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
      CROSS JOIN (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
      CROSS JOIN (SELECT 0 n UNION SELECT 1) c
    ) nums
    WHERE DATE('2026-02-02') + INTERVAL seq DAY <= '2026-06-30'
      AND WEEKDAY(DATE('2026-02-02') + INTERVAL seq DAY) < 5
  ) d ON gh.dia_semana = WEEKDAY(d.data_aula) + 1
  WHERE NOT EXISTS (
    SELECT 1 FROM calendario_letivo_eventos e
    WHERE e.calendario_id = v_cal
      AND e.tipo IN ('feriado','recesso','suspensao')
      AND d.data_aula BETWEEN e.data_inicio AND e.data_fim
  )
  AND NOT EXISTS (
    SELECT 1 FROM diario_aulas da WHERE da.grade_horaria_id = gh.id AND da.data_aula = d.data_aula
  );

  INSERT IGNORE INTO diario_frequencias (diario_aula_id, aluno_id, situacao, origem)
  SELECT da.id, m.aluno_id,
    CASE
      WHEN m.aluno_id % 17 = 0 AND WEEKDAY(da.data_aula) >= 3 THEN 'falta'
      WHEN (CRC32(CONCAT(m.aluno_id, '|', da.data_aula)) % 100) < 7 THEN 'falta'
      WHEN (CRC32(CONCAT(m.aluno_id, '|', da.data_aula)) % 100) < 10 THEN 'atraso'
      WHEN (CRC32(CONCAT(m.aluno_id, '|', da.data_aula)) % 100) < 12 THEN 'falta_justificada'
      WHEN (CRC32(CONCAT(m.aluno_id, '|', da.data_aula)) % 100) < 14 THEN 'saida_antecipada'
      ELSE 'presente'
    END,
    CASE
      WHEN (m.aluno_id % 17 = 0 AND WEEKDAY(da.data_aula) >= 3)
        OR (CRC32(CONCAT(m.aluno_id, '|', da.data_aula)) % 100) < 7
        THEN IF(m.aluno_id % 5 = 0, 'ajuste_gestao', 'manual_diario')
      WHEN (CRC32(CONCAT(m.aluno_id, '|', da.data_aula)) % 100) < 12 THEN 'ajuste_gestao'
      WHEN m.aluno_id % 5 = 0 THEN 'ajuste_gestao'
      ELSE 'entrada_saida'
    END
  FROM diario_aulas da
  INNER JOIN matricula m ON m.turma_id = da.turma_id AND m.status = 'ativa' AND m.ano_letivo_id = v_ano_id
  WHERE (da.conteudo_realizado LIKE 'ET %' OR da.observacoes LIKE 'ET-SEED%')
    AND da.data_aula BETWEEN '2026-02-02' AND '2026-06-30';

  -- ── Faltas agregadas ──────────────────────────────────────
  INSERT INTO faltas_eventos (nome, bimestre, ano_letivo, turmas_json, origem, created_by, ativo)
  SELECT 'ET Faltas 1º bimestre 2026', '1', 2026,
         (SELECT JSON_ARRAYAGG(id) FROM turmas WHERE ano_letivo = 2026), 'diario', v_admin, 1
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM faltas_eventos WHERE nome = 'ET Faltas 1º bimestre 2026' AND ativo = 1);
  INSERT INTO faltas_eventos (nome, bimestre, ano_letivo, turmas_json, origem, created_by, ativo)
  SELECT 'ET Faltas 2º bimestre 2026', '2', 2026,
         (SELECT JSON_ARRAYAGG(id) FROM turmas WHERE ano_letivo = 2026), 'diario', v_admin, 1
  FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM faltas_eventos WHERE nome = 'ET Faltas 2º bimestre 2026' AND ativo = 1);

  INSERT INTO faltas_lancamentos (evento_id, aluno_id, materia_id, faltas, created_by)
  SELECT e.id, x.aluno_id, x.materia_id, x.faltas, v_admin
  FROM faltas_eventos e
  INNER JOIN (
    SELECT df.aluno_id, da.materia_id,
           SUM(CASE WHEN df.situacao IN ('falta','falta_justificada') THEN 1 ELSE 0 END) faltas,
           CASE WHEN da.data_aula <= '2026-03-31' THEN 1 ELSE 2 END bim
    FROM diario_frequencias df
    INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
    WHERE (da.conteudo_realizado LIKE 'ET %' OR da.observacoes LIKE 'ET-SEED%')
      AND da.data_aula BETWEEN '2026-02-02' AND '2026-06-30'
    GROUP BY df.aluno_id, da.materia_id, CASE WHEN da.data_aula <= '2026-03-31' THEN 1 ELSE 2 END
    HAVING faltas > 0
  ) x ON e.bimestre = CAST(x.bim AS CHAR) AND e.ano_letivo = 2026 AND e.ativo = 1 AND e.origem = 'diario'
     AND e.nome LIKE 'ET Faltas %'
  ON DUPLICATE KEY UPDATE faltas = VALUES(faltas);

  -- ── Entrada / saída ───────────────────────────────────────
  INSERT IGNORE INTO presenca_eventos (aluno_id, tipo, ocorrido_em, origem, id_externo, identificador_bruto, processado_em)
  SELECT a.id, 'entrada',
         TIMESTAMP(d.data_aula, IF(t.turno = 'tarde',
           IF((CRC32(CONCAT(a.id, '|', d.data_aula)) % 100) BETWEEN 7 AND 9, '13:42:00', CONCAT('13:', LPAD(18 + (a.id % 10), 2, '0'), ':00')),
           IF((CRC32(CONCAT(a.id, '|', d.data_aula)) % 100) BETWEEN 7 AND 9, '07:42:00', CONCAT('07:', LPAD(18 + (a.id % 10), 2, '0'), ':00'))
         )),
         IF(a.id % 5 = 0, 'manual_secretaria', 'facial'),
         CONCAT('et-', a.id, '-', d.data_aula, '-entrada'),
         a.ra, NOW()
  FROM alunos a
  INNER JOIN turmas t ON t.id = a.turma_id
  INNER JOIN (
    SELECT DISTINCT turma_id, data_aula FROM diario_aulas
    WHERE conteudo_realizado LIKE 'ET %' OR observacoes LIKE 'ET-SEED%'
  ) d ON d.turma_id = a.turma_id
  WHERE a.nickname LIKE 'et.%'
    AND NOT (a.id % 17 = 0 AND WEEKDAY(d.data_aula) >= 3)
    AND (CRC32(CONCAT(a.id, '|', d.data_aula)) % 100) >= 7;

  INSERT IGNORE INTO presenca_eventos (aluno_id, tipo, ocorrido_em, origem, id_externo, identificador_bruto, processado_em)
  SELECT a.id, 'saida',
         TIMESTAMP(d.data_aula, IF(t.turno = 'tarde', CONCAT('18:', LPAD(2 + (a.id % 8), 2, '0'), ':00'), CONCAT('12:', LPAD(2 + (a.id % 8), 2, '0'), ':00'))),
         IF(a.id % 5 = 0, 'manual_secretaria', 'facial'),
         CONCAT('et-', a.id, '-', d.data_aula, '-saida'),
         a.ra, NOW()
  FROM alunos a
  INNER JOIN turmas t ON t.id = a.turma_id
  INNER JOIN (
    SELECT DISTINCT turma_id, data_aula FROM diario_aulas
    WHERE conteudo_realizado LIKE 'ET %' OR observacoes LIKE 'ET-SEED%'
  ) d ON d.turma_id = a.turma_id
  WHERE a.nickname LIKE 'et.%'
    AND EXISTS (
      SELECT 1 FROM presenca_eventos pe
      WHERE pe.id_externo = CONCAT('et-', a.id, '-', d.data_aula, '-entrada')
    );

  -- ── Eventos de nota B1/B2 ─────────────────────────────────
  SET v_t = 1;
  WHILE v_t <= 9 DO
    SET v_turma_id = ELT(v_t,
      (SELECT id FROM turmas WHERE nome = '1º A' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '1º B' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '1º C' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '2º A' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '2º B' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '2º C' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '3º A' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '3º B' AND ano_letivo = 2026),
      (SELECT id FROM turmas WHERE nome = '3º C' AND ano_letivo = 2026)
    );
    SET v_hora1 = IF(v_t <= 5, '08:00:00', '14:00:00');
    SET v_hora2 = IF(v_t <= 5, '09:30:00', '15:30:00');
    SET v_bim = 1;
    WHILE v_bim <= 2 DO
      SET v_ev = 1;
      WHILE v_ev <= 3 DO
        SET v_titulo = CONCAT('ET ', ELT(v_ev, 'Prova Bimestral', 'Trabalho', 'Participação'), ' — ',
          ELT(v_t, '1º A','1º B','1º C','2º A','2º B','2º C','3º A','3º B','3º C'), ' B', v_bim);
        SET v_data_ev = ELT((v_bim - 1) * 3 + v_ev, '2026-03-18','2026-03-04','2026-03-25','2026-06-10','2026-05-20','2026-06-20');
        IF NOT EXISTS (SELECT 1 FROM provas_blocos WHERE titulo = v_titulo AND deleted_at IS NULL) THEN
          INSERT INTO provas_blocos (
            titulo, descricao, data_prova, hora_inicio, hora_fim, criado_por, tipo_prova,
            formato_evento, configuracao_nota, ano_letivo, bimestre, tipo_avaliacao_id,
            visivel_no_portal_aluno, ativo, liberado, status, turma_id
          ) VALUES (
            v_titulo, 'Seed Educa Teste', v_data_ev, v_hora1, v_hora2, v_admin, 'original',
            'lancamento_nota', 'coordenacao_calcula', 2026, v_bim,
            ELT(v_ev, v_tipo_prova, v_tipo_trab, v_tipo_part),
            1, 1, 1, 'liberado', v_turma_id
          );
          SET v_bloco_id = LAST_INSERT_ID();
          INSERT IGNORE INTO provas_blocos_turmas (bloco_id, turma_id) VALUES (v_bloco_id, v_turma_id);
          INSERT INTO provas_blocos_professores (bloco_id, professor_id, materia_id, quantidade_questoes)
          SELECT v_bloco_id, p.id, m.id, 1
          FROM et_carga c
          INNER JOIN materias m ON m.nome = c.nome
          INNER JOIN professores p ON p.codigo_prof = CONCAT('ET-', ELT(c.ord,'LPO','MAT','ING','HIS','GEO','FIS','QUI','BIO','RED','EDF','SOC','FIL','ART'));
          INSERT IGNORE INTO provas_blocos_professores_turmas (bloco_professor_id, turma_id)
          SELECT bp.id, v_turma_id FROM provas_blocos_professores bp WHERE bp.bloco_id = v_bloco_id;
        ELSE
          SELECT id INTO v_bloco_id FROM provas_blocos WHERE titulo = v_titulo AND deleted_at IS NULL LIMIT 1;
        END IF;
        SET v_ev = v_ev + 1;
      END WHILE;

      -- regra de boletim (notas no evento; gerar boletim no admin se quiser o PDF)
      SET v_titulo = CONCAT('et-2026-', ELT(v_t,'em1a','em1b','em1c','em2a','em2b','em2c','em3a','em3b','em3c'), '-b', v_bim);
      IF NOT EXISTS (SELECT 1 FROM boletim_regras WHERE codigo = v_titulo) THEN
        INSERT INTO boletim_regras (nome, codigo, descricao_curta, formula_final, series_ids, turmas_ids, exibir_em, ano_letivo, bimestre, nota_minima_aprovacao, vis_aluno, vis_pais, vis_coordenacao, round_mode, decimal_places, default_data_inicio, default_data_fim, ativo)
        VALUES (
          CONCAT('Boletim ', ELT(v_t,'1º A','1º B','1º C','2º A','2º B','2º C','3º A','3º B','3º C'), ' B', v_bim),
          v_titulo, 'Seed teste EM',
          '(PROVA * 0.5) + (TRAB * 0.3) + (PART * 0.2)',
          JSON_ARRAY(ELT(v_t, v_s1,v_s1,v_s1,v_s2,v_s2,v_s2,v_s3,v_s3,v_s3)),
          JSON_ARRAY(v_turma_id),
          'boletim', 2026, v_bim, 6.00, 1, 1, 1, 'none', 2,
          IF(v_bim = 1, '2026-02-02', '2026-04-01'),
          IF(v_bim = 1, '2026-03-31', '2026-06-30'),
          1
        );
        SET v_regra = LAST_INSERT_ID();
        INSERT INTO boletim_componentes (regra_id, codigo, nome, source_type, calc_type, peso, blocos_ids, obrigatorio, ordem, ativo)
        SELECT v_regra, x.cod, x.nom, 'provas_sistema', 'media', x.peso,
               CAST((SELECT id FROM provas_blocos WHERE titulo = CONCAT('ET ', x.nom, ' — ', ELT(v_t,'1º A','1º B','1º C','2º A','2º B','2º C','3º A','3º B','3º C'), ' B', v_bim) AND deleted_at IS NULL LIMIT 1) AS CHAR),
               1, x.ord, 1
        FROM (
          SELECT 'PROVA' cod, 'Prova Bimestral' nom, 0.500 peso, 1 ord
          UNION ALL SELECT 'TRAB', 'Trabalho', 0.300, 2
          UNION ALL SELECT 'PART', 'Participação', 0.200, 3
        ) x;
      END IF;
      SET v_bim = v_bim + 1;
    END WHILE;
    SET v_t = v_t + 1;
  END WHILE;

  INSERT IGNORE INTO provas_blocos_notas_lancadas (bloco_id, professor_id, materia_id, turma_id, aluno_id, nota)
  SELECT pb.id, bp.professor_id, bp.materia_id, pbt.turma_id, a.id,
         ROUND(LEAST(10, GREATEST(2, 4 + (CRC32(CONCAT(a.id, '|', pb.id, '|', bp.materia_id)) % 61) / 10)), 1)
  FROM provas_blocos pb
  INNER JOIN provas_blocos_professores bp ON bp.bloco_id = pb.id
  INNER JOIN provas_blocos_turmas pbt ON pbt.bloco_id = pb.id
  INNER JOIN alunos a ON a.turma_id = pbt.turma_id AND a.nickname LIKE 'et.%'
  WHERE pb.titulo LIKE 'ET %' AND pb.deleted_at IS NULL;

  -- ── Planos de aula (1 por turma × matéria × bimestre) ─────
  INSERT INTO planos_aula (
    professor_id, materia_id, turma_id, data_aula, titulo, ano_disciplina,
    objetivos, conteudo, metodologia, avaliacao, status
  )
  SELECT g.professor_id, g.materia_id, g.turma_id,
         IF(b.bim = 1, '2026-02-02', '2026-04-01'),
         CONCAT('ET ', t.nome, ' ', m.nome, ' B', b.bim),
         CONCAT(t.serie, ' / ', m.nome),
         CONCAT('Desenvolver as habilidades de ', m.nome, ' no ', b.bim, 'º bimestre, com acompanhamento contínuo em sala.'),
         CONCAT('Sequência de aulas de ', m.nome, ' alinhada à matriz curricular (', t.nome, ', ', b.bim, 'º bimestre).'),
         'Exposição dialogada, exercícios em dupla, correção coletiva e registro no diário.',
         'Participação, trabalho e prova bimestral do evento de notas.',
         'aprovado'
  FROM (
    SELECT DISTINCT professor_id, materia_id, turma_id FROM grade_horaria
  ) g
  INNER JOIN turmas t ON t.id = g.turma_id AND t.ano_letivo = 2026 AND t.observacoes LIKE 'ET %'
  INNER JOIN materias m ON m.id = g.materia_id
  CROSS JOIN (SELECT 1 bim UNION ALL SELECT 2) b
  WHERE NOT EXISTS (
    SELECT 1 FROM planos_aula p
    WHERE p.titulo = CONCAT('ET ', t.nome, ' ', m.nome, ' B', b.bim) AND p.deleted_at IS NULL
  );

  UPDATE diario_aulas da
  INNER JOIN planos_aula pa
    ON pa.professor_id = da.professor_id AND pa.turma_id = da.turma_id AND pa.materia_id = da.materia_id
   AND pa.deleted_at IS NULL AND pa.titulo LIKE 'ET %'
   AND ((da.data_aula <= '2026-03-31' AND pa.titulo LIKE '% B1')
     OR (da.data_aula > '2026-03-31' AND pa.titulo LIKE '% B2'))
  SET da.plano_aula_id = pa.id
  WHERE (da.conteudo_realizado LIKE 'ET %' OR da.observacoes LIKE 'ET-SEED%')
    AND da.data_aula BETWEEN '2026-02-02' AND '2026-06-30';

  -- Evento de prova/trabalho/participação no diário do dia
  UPDATE diario_aulas da
  INNER JOIN provas_blocos pb
    ON pb.turma_id = da.turma_id AND pb.data_prova = da.data_aula
   AND pb.titulo LIKE 'ET %' AND pb.deleted_at IS NULL
  INNER JOIN provas_blocos_professores bp
    ON bp.bloco_id = pb.id AND bp.materia_id = da.materia_id AND bp.professor_id = da.professor_id
  SET da.evento_bloco_id = pb.id,
      da.tipo_aula = IF(pb.titulo LIKE 'ET Prova%', 'avaliacao', 'atividade'),
      da.execucao = 'conforme_planejado',
      da.conteudo_realizado = CONCAT('ET Aplicação em sala — ', pb.titulo),
      da.observacoes = CONCAT('ET-SEED|evento|', pb.id)
  WHERE da.data_aula BETWEEN '2026-02-02' AND '2026-06-30';

  -- Reexecução: enriquece aulas já inseridas sem ET-SEED
  UPDATE diario_aulas da
  INNER JOIN materias mat ON mat.id = da.materia_id
  INNER JOIN turmas t ON t.id = da.turma_id
  SET da.execucao = CASE
        WHEN (CRC32(CONCAT(da.grade_horaria_id, '|', da.data_aula, '|ex')) % 100) < 10 THEN 'parcial'
        WHEN (CRC32(CONCAT(da.grade_horaria_id, '|', da.data_aula, '|ex')) % 100) < 15 THEN 'alterado'
        ELSE 'conforme_planejado'
      END,
      da.conteudo_realizado = CONCAT('ET ', mat.nome, ' — ',
        CASE
          WHEN (CRC32(CONCAT(da.grade_horaria_id, '|', da.data_aula, '|ex')) % 100) < 10
            THEN 'conteúdo iniciado; restante na próxima aula.'
          WHEN (CRC32(CONCAT(da.grade_horaria_id, '|', da.data_aula, '|ex')) % 100) < 15
            THEN 'sequência ajustada em sala (atividade extra).'
          ELSE CONCAT('aula ministrada conforme o plano (', t.nome, ').')
        END
      ),
      da.observacoes = CONCAT('ET-SEED|',
        CASE
          WHEN (CRC32(CONCAT(da.grade_horaria_id, '|', da.data_aula, '|ex')) % 100) < 10 THEN 'parcial'
          WHEN (CRC32(CONCAT(da.grade_horaria_id, '|', da.data_aula, '|ex')) % 100) < 15 THEN 'alterado'
          ELSE 'ok'
        END
      )
  WHERE t.observacoes LIKE 'ET %'
    AND da.evento_bloco_id IS NULL
    AND (da.observacoes IS NULL OR da.observacoes NOT LIKE 'ET-SEED|evento%');

  UPDATE diario_frequencias df
  INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
  SET df.situacao = CASE
        WHEN df.aluno_id % 17 = 0 AND WEEKDAY(da.data_aula) >= 3 THEN 'falta'
        WHEN (CRC32(CONCAT(df.aluno_id, '|', da.data_aula)) % 100) < 7 THEN 'falta'
        WHEN (CRC32(CONCAT(df.aluno_id, '|', da.data_aula)) % 100) < 10 THEN 'atraso'
        WHEN (CRC32(CONCAT(df.aluno_id, '|', da.data_aula)) % 100) < 12 THEN 'falta_justificada'
        WHEN (CRC32(CONCAT(df.aluno_id, '|', da.data_aula)) % 100) < 14 THEN 'saida_antecipada'
        ELSE 'presente'
      END,
      df.origem = CASE
        WHEN df.aluno_id % 17 = 0 AND WEEKDAY(da.data_aula) >= 3 THEN IF(df.aluno_id % 5 = 0, 'ajuste_gestao', 'manual_diario')
        WHEN (CRC32(CONCAT(df.aluno_id, '|', da.data_aula)) % 100) < 7 THEN IF(df.aluno_id % 5 = 0, 'ajuste_gestao', 'manual_diario')
        WHEN (CRC32(CONCAT(df.aluno_id, '|', da.data_aula)) % 100) < 12 THEN 'ajuste_gestao'
        WHEN df.aluno_id % 5 = 0 THEN 'ajuste_gestao'
        ELSE 'entrada_saida'
      END
  WHERE da.conteudo_realizado LIKE 'ET %' OR da.observacoes LIKE 'ET-SEED%';
END$$

DELIMITER ;

CALL seed_escola_teste_em();
DROP PROCEDURE IF EXISTS seed_escola_teste_em;
