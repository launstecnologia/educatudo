-- =============================================================================
-- Seed Santa Maria 2024 — só a aluna Ana Luiza (1ª série A).
-- Cria: ano letivo bimestre, matérias, turma, aluna, regra de aprovação,
-- recuperações (semestral + prova final), eventos avaliativos (PP/PU/CH/PE
-- nos 4 bimestres + R1/R2/RF), notas e faltas.
-- NÃO cria: demonstrativo de notas nem boletim (monte do zero no admin).
-- Senha da aluna: Teste@123
-- Prefixo: SM24 | nickname sm24.analuiza | RA 24254
--
-- Master → Migrations → Colégio Educa → Escolher → marque SÓ este arquivo.
-- Não entra em "Executar todas" (nome importar_dados_*).
-- Pode rodar de novo: apaga o próprio seed e recria.
-- Para só esvaziar: 2026_11_10_importar_dados_santa_maria_ana_2024_apagar.sql
-- =============================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '-03:00';

DROP PROCEDURE IF EXISTS apagar_santa_maria_ana_2024;
DROP PROCEDURE IF EXISTS seed_santa_maria_ana_2024;

DELIMITER $$

CREATE PROCEDURE apagar_santa_maria_ana_2024()
BEGIN
  DELETE l FROM faltas_lancamentos l
  INNER JOIN faltas_eventos e ON e.id = l.evento_id
  WHERE e.nome LIKE 'SM24 %';

  DELETE FROM faltas_eventos WHERE nome LIKE 'SM24 %';

  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'provas_blocos_notas_lancadas_log') > 0 THEN
    DELETE lg FROM provas_blocos_notas_lancadas_log lg
    INNER JOIN provas_blocos pb ON pb.id = lg.bloco_id
    WHERE pb.titulo LIKE 'SM24 %' OR pb.descricao = 'SM24';
  END IF;

  DELETE n FROM provas_blocos_notas_lancadas n
  INNER JOIN provas_blocos pb ON pb.id = n.bloco_id
  WHERE pb.titulo LIKE 'SM24 %' OR pb.descricao = 'SM24';

  DELETE pt FROM provas_blocos_professores_turmas pt
  INNER JOIN provas_blocos_professores bp ON bp.id = pt.bloco_professor_id
  INNER JOIN provas_blocos pb ON pb.id = bp.bloco_id
  WHERE pb.titulo LIKE 'SM24 %' OR pb.descricao = 'SM24';

  DELETE bp FROM provas_blocos_professores bp
  INNER JOIN provas_blocos pb ON pb.id = bp.bloco_id
  WHERE pb.titulo LIKE 'SM24 %' OR pb.descricao = 'SM24';

  DELETE bt FROM provas_blocos_turmas bt
  INNER JOIN provas_blocos pb ON pb.id = bt.bloco_id
  WHERE pb.titulo LIKE 'SM24 %' OR pb.descricao = 'SM24';

  DELETE FROM provas_blocos WHERE titulo LIKE 'SM24 %' OR descricao = 'SM24';

  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos_avaliativos_alunos') > 0 THEN
    DELETE a FROM eventos_avaliativos_alunos a
    INNER JOIN eventos_avaliativos e ON e.id = a.evento_id
    WHERE e.nome LIKE 'SM24 %' OR e.descricao = 'SM24';
  END IF;

  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eventos_avaliativos') > 0 THEN
    DELETE FROM eventos_avaliativos WHERE nome LIKE 'SM24 %' OR descricao = 'SM24';
  END IF;

  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recuperacoes') > 0 THEN
    DELETE FROM recuperacoes
     WHERE regra_id IN (SELECT id FROM regras_academicas WHERE codigo = 'sm24-aprovacao-2024');
  END IF;

  DELETE FROM regras_academicas_materias
  WHERE regra_id IN (SELECT id FROM regras_academicas WHERE codigo = 'sm24-aprovacao-2024');
  DELETE FROM regras_academicas_series
  WHERE regra_id IN (SELECT id FROM regras_academicas WHERE codigo = 'sm24-aprovacao-2024');
  DELETE FROM regras_academicas WHERE codigo = 'sm24-aprovacao-2024';

  DELETE FROM provas_tipos_avaliacao
  WHERE nome IN ('SM24 PP','SM24 PU','SM24 CH','SM24 PE','SM24 REC','SM24 PF') AND deleted_at IS NULL;

  DELETE m FROM matricula m
  INNER JOIN alunos a ON a.id = m.aluno_id
  WHERE a.nickname = 'sm24.analuiza' OR a.ra = '24254';

  DELETE FROM alunos_turma_chamada
  WHERE aluno_id IN (SELECT id FROM alunos WHERE nickname = 'sm24.analuiza' OR ra = '24254');

  DELETE FROM alunos WHERE nickname = 'sm24.analuiza' OR ra = '24254';

  DELETE FROM turmas WHERE nome = '1A Santa Maria' AND ano_letivo = 2024 AND observacoes LIKE 'SM24 %';

  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agrupamentos_componentes_itens') > 0 THEN
    DELETE i FROM agrupamentos_componentes_itens i
    INNER JOIN agrupamentos_componentes a ON a.id = i.agrupamento_id
    WHERE a.nome LIKE 'SM24 %';
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'agrupamentos_componentes') > 0 THEN
    DELETE FROM agrupamentos_componentes WHERE nome LIKE 'SM24 %';
  END IF;

  DELETE FROM materias WHERE codigo LIKE 'SM24-%';
  DELETE FROM professores WHERE codigo_prof = 'SM24-PROF';
END$$

CREATE PROCEDURE seed_santa_maria_ana_2024()
BEGIN
  DECLARE v_admin INT DEFAULT 0;
  DECLARE v_ano_id INT DEFAULT 0;
  DECLARE v_serie INT DEFAULT 0;
  DECLARE v_curso INT DEFAULT 0;
  DECLARE v_matriz INT DEFAULT 0;
  DECLARE v_unidade INT DEFAULT 0;
  DECLARE v_turma INT DEFAULT 0;
  DECLARE v_aluno INT DEFAULT 0;
  DECLARE v_prof INT DEFAULT 0;
  DECLARE v_regra INT DEFAULT 0;
  DECLARE v_tipo_pp INT DEFAULT 0;
  DECLARE v_tipo_pu INT DEFAULT 0;
  DECLARE v_tipo_ch INT DEFAULT 0;
  DECLARE v_tipo_pe INT DEFAULT 0;
  DECLARE v_tipo_rec INT DEFAULT 0;
  DECLARE v_tipo_pf INT DEFAULT 0;
  DECLARE v_bim INT DEFAULT 1;
  DECLARE v_ev INT DEFAULT 0;
  DECLARE v_bloco INT DEFAULT 0;
  DECLARE v_evento INT DEFAULT 0;
  DECLARE v_hash VARCHAR(255) DEFAULT '$2y$12$ejC1hLe4O80BQZeBjvWnbO3o2iUOxtSX6.QfX3ZvMygFkeC4sDZaO';
  DECLARE v_data VARCHAR(10);
  DECLARE v_nome_ev VARCHAR(120);
  DECLARE v_sigla VARCHAR(12);
  DECLARE v_tipo INT DEFAULT 0;

  CALL apagar_santa_maria_ana_2024();

  SET v_admin = (
    SELECT id FROM usuarios
    WHERE (email LIKE 'admin@%' OR tipo = 'admin_escola') AND COALESCE(ativo,1) = 1
    ORDER BY id ASC LIMIT 1
  );
  IF v_admin IS NULL OR v_admin <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'SM24: nenhum admin ativo na escola.';
  END IF;

  INSERT INTO ano_letivo (ano, data_inicio, data_fim, ativo, periodo_tipo)
  SELECT 2024, '2024-02-01', '2024-12-20', 1, 'bimestre' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM ano_letivo WHERE ano = 2024);
  UPDATE ano_letivo
     SET periodo_tipo = 'bimestre', data_inicio = '2024-02-01', data_fim = '2024-12-20', ativo = 1
   WHERE ano = 2024;
  SET v_ano_id = (SELECT id FROM ano_letivo WHERE ano = 2024 LIMIT 1);

  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ano_letivo_periodos') > 0 THEN
    DELETE FROM ano_letivo_periodos WHERE ano_letivo_id = v_ano_id;
    INSERT INTO ano_letivo_periodos (ano_letivo_id, numero, data_inicio, data_fim) VALUES
      (v_ano_id, 1, '2024-02-01', '2024-04-15'),
      (v_ano_id, 2, '2024-04-16', '2024-06-30'),
      (v_ano_id, 3, '2024-08-01', '2024-10-15'),
      (v_ano_id, 4, '2024-10-16', '2024-12-20');
  END IF;

  SET v_serie = (
    SELECT id FROM serie
    WHERE nome LIKE '%1%Série%EM%' OR nome LIKE '%1ª Série%EM%' OR nome LIKE '1% Série EM'
    ORDER BY id ASC LIMIT 1
  );
  IF v_serie IS NULL OR v_serie <= 0 THEN
    SET v_serie = (SELECT id FROM serie WHERE nome LIKE '%1%' ORDER BY id ASC LIMIT 1);
  END IF;
  SET v_curso = (
    SELECT id FROM curso WHERE nome LIKE '%Médio%' OR nome LIKE '%Medio%' ORDER BY id ASC LIMIT 1
  );
  SET v_matriz = (SELECT id FROM matrizes_curriculares ORDER BY id ASC LIMIT 1);
  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unidades') > 0 THEN
    SET v_unidade = (SELECT id FROM unidades ORDER BY id ASC LIMIT 1);
  END IF;

  -- Matérias (pais + filhos). Código SM24-* exclusivo deste seed.
  INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo, pai_id)
  SELECT d.n, d.c, d.s, d.area, 'formacao_geral', 'obrigatoria', 'Seed SM24', '#4F46E5', 100, d.av, 1, 1, 1, 1, NULL
  FROM (
    SELECT 'História' n, 'SM24-HIS' c, 'HIS' s, 'ciencias_humanas' area, 0 av
    UNION ALL SELECT 'Matemática','SM24-MAT','MAT','matematica',0
    UNION ALL SELECT 'Física','SM24-FIS','FIS','ciencias_natureza',0
    UNION ALL SELECT 'Química','SM24-QUI','QUI','ciencias_natureza',0
    UNION ALL SELECT 'Biologia','SM24-BIO','BIO','ciencias_natureza',0
    UNION ALL SELECT 'Língua Portuguesa','SM24-LP','LP','linguagens',1
    UNION ALL SELECT 'Redação','SM24-RED','RED','linguagens',1
    UNION ALL SELECT 'Literatura','SM24-LIT','LIT','linguagens',1
    UNION ALL SELECT 'Arte','SM24-ART','ART','arte',1
    UNION ALL SELECT 'Geografia','SM24-GEO','GEO','ciencias_humanas',1
    UNION ALL SELECT 'Sociologia','SM24-SOC','SOC','ciencias_humanas',1
    UNION ALL SELECT 'Filosofia','SM24-FIL','FIL','ciencias_humanas',1
    UNION ALL SELECT 'Língua Inglesa','SM24-ING','ING','linguagens',1
    UNION ALL SELECT 'Educação Física','SM24-EDF','EDF','linguagens',1
  ) d
  WHERE NOT EXISTS (SELECT 1 FROM materias m WHERE m.codigo = d.c);

  INSERT INTO materias (nome, codigo, sigla, area_conhecimento, tipo, etapa_medio, descricao, cor, ordem, permite_avaliacao, permite_frequencia, permite_plano_aula, permite_diario, ativo, pai_id)
  SELECT d.n, d.c, d.s, d.area, 'formacao_geral', 'obrigatoria', 'Seed SM24', '#4F46E5', 101, 1, 1, 1, 1, 1,
         (SELECT id FROM materias WHERE codigo = d.pai LIMIT 1)
  FROM (
    SELECT 'História 1' n, 'SM24-HIS1' c, 'HI1' s, 'ciencias_humanas' area, 'SM24-HIS' pai
    UNION ALL SELECT 'História 2','SM24-HIS2','HI2','ciencias_humanas','SM24-HIS'
    UNION ALL SELECT 'Matemática 1','SM24-MAT1','MA1','matematica','SM24-MAT'
    UNION ALL SELECT 'Matemática 2','SM24-MAT2','MA2','matematica','SM24-MAT'
    UNION ALL SELECT 'Física 1','SM24-FIS1','FI1','ciencias_natureza','SM24-FIS'
    UNION ALL SELECT 'Física 2','SM24-FIS2','FI2','ciencias_natureza','SM24-FIS'
    UNION ALL SELECT 'Química 1','SM24-QUI1','QU1','ciencias_natureza','SM24-QUI'
    UNION ALL SELECT 'Química 2','SM24-QUI2','QU2','ciencias_natureza','SM24-QUI'
    UNION ALL SELECT 'Biologia 1','SM24-BIO1','BI1','ciencias_natureza','SM24-BIO'
    UNION ALL SELECT 'Biologia 2','SM24-BIO2','BI2','ciencias_natureza','SM24-BIO'
  ) d
  WHERE NOT EXISTS (SELECT 1 FROM materias m WHERE m.codigo = d.c);

  INSERT INTO professores (nome, email, senha_hash, codigo_prof, materias, turmas, ativo, pagante, password)
  SELECT 'SM24 Professor Geral', 'sm24.prof@escola.local', v_hash, 'SM24-PROF',
         JSON_ARRAY('Matemática','História','Língua Portuguesa'), JSON_ARRAY(), 1, 0, ''
  FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM professores WHERE codigo_prof = 'SM24-PROF');
  SET v_prof = (SELECT id FROM professores WHERE codigo_prof = 'SM24-PROF' LIMIT 1);
  IF v_prof IS NULL OR v_prof <= 0 THEN
    SET v_prof = (SELECT id FROM professores WHERE ativo = 1 ORDER BY id ASC LIMIT 1);
  END IF;
  IF v_prof IS NULL OR v_prof <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'SM24: nenhum professor disponível.';
  END IF;

  INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id, curso_novo_id, ativo, turno, vagas, observacoes)
  VALUES ('1A Santa Maria', 2024, v_ano_id, '1ª Série EM', v_serie, v_matriz, v_curso, 1, 'manha', 40, 'SM24 boletim Santa Maria');
  SET v_turma = LAST_INSERT_ID();

  INSERT INTO alunos (
    nome, nickname, email, senha_hash, ra, turma_id, serie, unidade_id, data_nasc, ativo, pagante, status, password, primeiro_acesso
  ) VALUES (
    'Ana Luiza Oliveira De Souza', 'sm24.analuiza', 'sm24.analuiza@escola.local', v_hash, '24254',
    v_turma, '1ª Série EM', v_unidade, '2009-05-12', 1, 1, 'ACTIVE', '', 0
  );
  SET v_aluno = LAST_INSERT_ID();

  INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, status)
  VALUES (v_aluno, v_turma, v_ano_id, '2024-02-01', 'ativa');

  INSERT INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem, chave_quadro, origem, registro_evento, criterio_fechamento, escala_max)
  SELECT d.n, d.d, 1, d.o, d.k, 'lancamento_direto', 'nota', 'ultima', 10.00
  FROM (
    SELECT 'SM24 PP' n, 'SM24 Prova Parcial' d, 10 o, 'sm24_pp' k
    UNION ALL SELECT 'SM24 PU', 'SM24 Prova de Unidade', 11, 'sm24_pu'
    UNION ALL SELECT 'SM24 CH', 'SM24 2ª Chamada', 12, 'sm24_ch'
    UNION ALL SELECT 'SM24 PE', 'SM24 Ponto Extra', 13, 'sm24_pe'
    UNION ALL SELECT 'SM24 REC', 'SM24 Recuperação semestral', 14, 'sm24_rec'
    UNION ALL SELECT 'SM24 PF', 'SM24 Prova Final', 15, 'sm24_pf'
  ) d
  WHERE NOT EXISTS (SELECT 1 FROM provas_tipos_avaliacao t WHERE t.nome = d.n AND t.deleted_at IS NULL);

  SET v_tipo_pp = (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'SM24 PP' AND deleted_at IS NULL LIMIT 1);
  SET v_tipo_pu = (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'SM24 PU' AND deleted_at IS NULL LIMIT 1);
  SET v_tipo_ch = (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'SM24 CH' AND deleted_at IS NULL LIMIT 1);
  SET v_tipo_pe = (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'SM24 PE' AND deleted_at IS NULL LIMIT 1);
  SET v_tipo_rec = (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'SM24 REC' AND deleted_at IS NULL LIMIT 1);
  SET v_tipo_pf = (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'SM24 PF' AND deleted_at IS NULL LIMIT 1);

  INSERT INTO regras_academicas (
    nome, codigo, ano_letivo, serie_id, periodo_tipo, media_minima, escala, frequencia_minima,
    usar_frequencia, round_mode, decimal_places, formula_final, recuperacao_tipo, recuperacao_composicao,
    finalidade, vis_aluno, vis_pais, ativo, observacoes
  ) VALUES (
    'Aprovação Santa Maria 2024', 'sm24-aprovacao-2024', 2024, v_serie, 'bimestre', 7.00, '10', 75.00,
    0, 'none', 1, '(m1 + m2 + m3 + m4) / 4', 'periodo', 'maior_nota',
    'oficial', 1, 1, 1, 'SM24 Seed Ana — sem demonstrativo/boletim.'
  );
  SET v_regra = LAST_INSERT_ID();

  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regras_academicas_materias') > 0 THEN
    INSERT IGNORE INTO regras_academicas_materias (regra_id, materia_id)
    SELECT v_regra, m.id FROM materias m WHERE m.codigo LIKE 'SM24-%' AND m.permite_avaliacao = 1;
  END IF;
  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'regras_academicas_series') > 0 AND v_serie > 0 THEN
    INSERT IGNORE INTO regras_academicas_series (regra_id, serie_id) VALUES (v_regra, v_serie);
  END IF;

  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recuperacoes') > 0 THEN
    INSERT INTO recuperacoes (ano_letivo, regra_id, nome, quando, aplica_em, nota_minima, nota_maxima, efeito, formula, permite_aumentar, ordem)
    VALUES
      (2024, v_regra, 'Recuperação semestral', 'par', 'par', 7.00, 10.00, 'maior_nota', NULL, 0, 1),
      (2024, v_regra, 'Prova Final', 'final', NULL, 7.00, 10.00, 'maior_nota', NULL, 0, 2);
  END IF;

  DROP TEMPORARY TABLE IF EXISTS sm24_tmp_notas;
  CREATE TEMPORARY TABLE sm24_tmp_notas (
    materia VARCHAR(80) NOT NULL,
    bim TINYINT NOT NULL,
    tipo VARCHAR(4) NOT NULL,
    nota DECIMAL(6,2) NULL,
    PRIMARY KEY (materia, bim, tipo)
  );
  INSERT INTO sm24_tmp_notas (materia, bim, tipo, nota) VALUES
    ('Língua Portuguesa', 1, 'pp', 8.5),
    ('Língua Portuguesa', 1, 'pu', 7.5),
    ('Língua Portuguesa', 1, 'pe', NULL),
    ('Língua Portuguesa', 1, 'ch', NULL),
    ('Língua Portuguesa', 2, 'pp', 7.0),
    ('Língua Portuguesa', 2, 'pu', 8.8),
    ('Língua Portuguesa', 2, 'pe', NULL),
    ('Língua Portuguesa', 2, 'ch', NULL),
    ('Língua Portuguesa', 3, 'pp', 8.5),
    ('Língua Portuguesa', 3, 'pu', 7.1),
    ('Língua Portuguesa', 3, 'pe', NULL),
    ('Língua Portuguesa', 3, 'ch', NULL),
    ('Língua Portuguesa', 4, 'pp', 8.5),
    ('Língua Portuguesa', 4, 'pu', NULL),
    ('Língua Portuguesa', 4, 'pe', NULL),
    ('Língua Portuguesa', 4, 'ch', NULL),
    ('Redação', 1, 'pp', 9.3),
    ('Redação', 1, 'pu', 8.6),
    ('Redação', 1, 'pe', NULL),
    ('Redação', 1, 'ch', NULL),
    ('Redação', 2, 'pp', 9.7),
    ('Redação', 2, 'pu', 7.0),
    ('Redação', 2, 'pe', NULL),
    ('Redação', 2, 'ch', NULL),
    ('Redação', 3, 'pp', 6.5),
    ('Redação', 3, 'pu', 8.1),
    ('Redação', 3, 'pe', NULL),
    ('Redação', 3, 'ch', NULL),
    ('Redação', 4, 'pp', 9.0),
    ('Redação', 4, 'pu', NULL),
    ('Redação', 4, 'pe', NULL),
    ('Redação', 4, 'ch', NULL),
    ('Literatura', 1, 'pp', 9.0),
    ('Literatura', 1, 'pu', 7.5),
    ('Literatura', 1, 'pe', NULL),
    ('Literatura', 1, 'ch', NULL),
    ('Literatura', 2, 'pp', 9.5),
    ('Literatura', 2, 'pu', 8.8),
    ('Literatura', 2, 'pe', NULL),
    ('Literatura', 2, 'ch', NULL),
    ('Literatura', 3, 'pp', 8.0),
    ('Literatura', 3, 'pu', 7.1),
    ('Literatura', 3, 'pe', NULL),
    ('Literatura', 3, 'ch', NULL),
    ('Literatura', 4, 'pp', 10.0),
    ('Literatura', 4, 'pu', NULL),
    ('Literatura', 4, 'pe', NULL),
    ('Literatura', 4, 'ch', NULL),
    ('Arte', 1, 'pp', 10.0),
    ('Arte', 1, 'pu', 7.5),
    ('Arte', 1, 'pe', NULL),
    ('Arte', 1, 'ch', NULL),
    ('Arte', 2, 'pp', 6.5),
    ('Arte', 2, 'pu', 8.8),
    ('Arte', 2, 'pe', NULL),
    ('Arte', 2, 'ch', NULL),
    ('Arte', 3, 'pp', 10.0),
    ('Arte', 3, 'pu', 7.1),
    ('Arte', 3, 'pe', NULL),
    ('Arte', 3, 'ch', NULL),
    ('Arte', 4, 'pp', 8.0),
    ('Arte', 4, 'pu', NULL),
    ('Arte', 4, 'pe', NULL),
    ('Arte', 4, 'ch', NULL),
    ('História 1', 1, 'pp', 5.0),
    ('História 1', 1, 'pu', 4.1),
    ('História 1', 1, 'pe', NULL),
    ('História 1', 1, 'ch', NULL),
    ('História 1', 2, 'pp', 5.0),
    ('História 1', 2, 'pu', 4.6),
    ('História 1', 2, 'pe', NULL),
    ('História 1', 2, 'ch', NULL),
    ('História 1', 3, 'pp', 4.0),
    ('História 1', 3, 'pu', 4.8),
    ('História 1', 3, 'pe', NULL),
    ('História 1', 3, 'ch', NULL),
    ('História 1', 4, 'pp', NULL),
    ('História 1', 4, 'pu', NULL),
    ('História 1', 4, 'pe', NULL),
    ('História 1', 4, 'ch', NULL),
    ('História 2', 1, 'pp', 5.0),
    ('História 2', 1, 'pu', 4.0),
    ('História 2', 1, 'pe', NULL),
    ('História 2', 1, 'ch', NULL),
    ('História 2', 2, 'pp', 5.0),
    ('História 2', 2, 'pu', 4.5),
    ('História 2', 2, 'pe', NULL),
    ('História 2', 2, 'ch', NULL),
    ('História 2', 3, 'pp', 4.0),
    ('História 2', 3, 'pu', 4.8),
    ('História 2', 3, 'pe', NULL),
    ('História 2', 3, 'ch', NULL),
    ('História 2', 4, 'pp', 2.0),
    ('História 2', 4, 'pu', NULL),
    ('História 2', 4, 'pe', NULL),
    ('História 2', 4, 'ch', NULL),
    ('Geografia', 1, 'pp', 9.0),
    ('Geografia', 1, 'pu', 8.1),
    ('Geografia', 1, 'pe', NULL),
    ('Geografia', 1, 'ch', NULL),
    ('Geografia', 2, 'pp', 10.0),
    ('Geografia', 2, 'pu', 9.1),
    ('Geografia', 2, 'pe', NULL),
    ('Geografia', 2, 'ch', NULL),
    ('Geografia', 3, 'pp', 10.0),
    ('Geografia', 3, 'pu', 9.6),
    ('Geografia', 3, 'pe', NULL),
    ('Geografia', 3, 'ch', NULL),
    ('Geografia', 4, 'pp', 8.5),
    ('Geografia', 4, 'pu', NULL),
    ('Geografia', 4, 'pe', NULL),
    ('Geografia', 4, 'ch', NULL),
    ('Sociologia', 1, 'pp', 8.5),
    ('Sociologia', 1, 'pu', 8.1),
    ('Sociologia', 1, 'pe', NULL),
    ('Sociologia', 1, 'ch', NULL),
    ('Sociologia', 2, 'pp', 8.0),
    ('Sociologia', 2, 'pu', 9.1),
    ('Sociologia', 2, 'pe', NULL),
    ('Sociologia', 2, 'ch', NULL),
    ('Sociologia', 3, 'pp', 9.0),
    ('Sociologia', 3, 'pu', 9.6),
    ('Sociologia', 3, 'pe', NULL),
    ('Sociologia', 3, 'ch', NULL),
    ('Sociologia', 4, 'pp', 6.5),
    ('Sociologia', 4, 'pu', NULL),
    ('Sociologia', 4, 'pe', NULL),
    ('Sociologia', 4, 'ch', NULL),
    ('Filosofia', 1, 'pp', 8.5),
    ('Filosofia', 1, 'pu', 8.1),
    ('Filosofia', 1, 'pe', NULL),
    ('Filosofia', 1, 'ch', NULL),
    ('Filosofia', 2, 'pp', 6.0),
    ('Filosofia', 2, 'pu', 9.1),
    ('Filosofia', 2, 'pe', NULL),
    ('Filosofia', 2, 'ch', NULL),
    ('Filosofia', 3, 'pp', 9.5),
    ('Filosofia', 3, 'pu', 9.6),
    ('Filosofia', 3, 'pe', NULL),
    ('Filosofia', 3, 'ch', NULL),
    ('Filosofia', 4, 'pp', NULL),
    ('Filosofia', 4, 'pu', NULL),
    ('Filosofia', 4, 'pe', NULL),
    ('Filosofia', 4, 'ch', NULL),
    ('Matemática 1', 1, 'pp', 3.0),
    ('Matemática 1', 1, 'pu', 1.7),
    ('Matemática 1', 1, 'pe', 0.5),
    ('Matemática 1', 1, 'ch', NULL),
    ('Matemática 1', 2, 'pp', 3.0),
    ('Matemática 1', 2, 'pu', 2.4),
    ('Matemática 1', 2, 'pe', 0.5),
    ('Matemática 1', 2, 'ch', NULL),
    ('Matemática 1', 3, 'pp', 4.0),
    ('Matemática 1', 3, 'pu', 3.1),
    ('Matemática 1', 3, 'pe', 0.5),
    ('Matemática 1', 3, 'ch', NULL),
    ('Matemática 1', 4, 'pp', NULL),
    ('Matemática 1', 4, 'pu', NULL),
    ('Matemática 1', 4, 'pe', NULL),
    ('Matemática 1', 4, 'ch', NULL),
    ('Matemática 1', 81, 'rec', 3.5),
    ('Matemática 2', 1, 'pp', 2.1),
    ('Matemática 2', 1, 'pu', 1.7),
    ('Matemática 2', 1, 'pe', NULL),
    ('Matemática 2', 1, 'ch', NULL),
    ('Matemática 2', 2, 'pp', 0.5),
    ('Matemática 2', 2, 'pu', 2.4),
    ('Matemática 2', 2, 'pe', NULL),
    ('Matemática 2', 2, 'ch', NULL),
    ('Matemática 2', 3, 'pp', 4.9),
    ('Matemática 2', 3, 'pu', 3.1),
    ('Matemática 2', 3, 'pe', NULL),
    ('Matemática 2', 3, 'ch', NULL),
    ('Matemática 2', 4, 'pp', NULL),
    ('Matemática 2', 4, 'pu', NULL),
    ('Matemática 2', 4, 'pe', NULL),
    ('Matemática 2', 4, 'ch', NULL),
    ('Matemática 2', 81, 'rec', 3.5),
    ('Física 1', 1, 'pp', 3.0),
    ('Física 1', 1, 'pu', 2.8),
    ('Física 1', 1, 'pe', NULL),
    ('Física 1', 1, 'ch', NULL),
    ('Física 1', 2, 'pp', 2.1),
    ('Física 1', 2, 'pu', 3.2),
    ('Física 1', 2, 'pe', 0.5),
    ('Física 1', 2, 'ch', NULL),
    ('Física 1', 3, 'pp', NULL),
    ('Física 1', 3, 'pu', 3.2),
    ('Física 1', 3, 'pe', 0.5),
    ('Física 1', 3, 'ch', 2.3),
    ('Física 1', 4, 'pp', NULL),
    ('Física 1', 4, 'pu', NULL),
    ('Física 1', 4, 'pe', NULL),
    ('Física 1', 4, 'ch', NULL),
    ('Física 1', 81, 'rec', 3.5),
    ('Física 2', 1, 'pp', 4.0),
    ('Física 2', 1, 'pu', 2.7),
    ('Física 2', 1, 'pe', NULL),
    ('Física 2', 1, 'ch', NULL),
    ('Física 2', 2, 'pp', 3.0),
    ('Física 2', 2, 'pu', 3.1),
    ('Física 2', 2, 'pe', NULL),
    ('Física 2', 2, 'ch', NULL),
    ('Física 2', 3, 'pp', NULL),
    ('Física 2', 3, 'pu', 3.1),
    ('Física 2', 3, 'pe', NULL),
    ('Física 2', 3, 'ch', 2.3),
    ('Física 2', 4, 'pp', NULL),
    ('Física 2', 4, 'pu', NULL),
    ('Física 2', 4, 'pe', NULL),
    ('Física 2', 4, 'ch', NULL),
    ('Física 2', 81, 'rec', 3.5),
    ('Química 1', 1, 'pp', 2.5),
    ('Química 1', 1, 'pu', 2.8),
    ('Química 1', 1, 'pe', NULL),
    ('Química 1', 1, 'ch', NULL),
    ('Química 1', 2, 'pp', 2.0),
    ('Química 1', 2, 'pu', 3.2),
    ('Química 1', 2, 'pe', NULL),
    ('Química 1', 2, 'ch', NULL),
    ('Química 1', 3, 'pp', NULL),
    ('Química 1', 3, 'pu', 3.2),
    ('Química 1', 3, 'pe', NULL),
    ('Química 1', 3, 'ch', 2.3),
    ('Química 1', 4, 'pp', NULL),
    ('Química 1', 4, 'pu', NULL),
    ('Química 1', 4, 'pe', NULL),
    ('Química 1', 4, 'ch', NULL),
    ('Química 1', 81, 'rec', 5.0),
    ('Química 2', 1, 'pp', 5.0),
    ('Química 2', 1, 'pu', 2.7),
    ('Química 2', 1, 'pe', NULL),
    ('Química 2', 1, 'ch', NULL),
    ('Química 2', 2, 'pp', 3.0),
    ('Química 2', 2, 'pu', 3.1),
    ('Química 2', 2, 'pe', NULL),
    ('Química 2', 2, 'ch', NULL),
    ('Química 2', 3, 'pp', NULL),
    ('Química 2', 3, 'pu', 3.1),
    ('Química 2', 3, 'pe', NULL),
    ('Química 2', 3, 'ch', 2.3),
    ('Química 2', 4, 'pp', NULL),
    ('Química 2', 4, 'pu', NULL),
    ('Química 2', 4, 'pe', NULL),
    ('Química 2', 4, 'ch', NULL),
    ('Química 2', 81, 'rec', 5.0),
    ('Biologia 1', 1, 'pp', 2.0),
    ('Biologia 1', 1, 'pu', 2.8),
    ('Biologia 1', 1, 'pe', 0.5),
    ('Biologia 1', 1, 'ch', NULL),
    ('Biologia 1', 2, 'pp', 3.4),
    ('Biologia 1', 2, 'pu', 3.2),
    ('Biologia 1', 2, 'pe', NULL),
    ('Biologia 1', 2, 'ch', NULL),
    ('Biologia 1', 3, 'pp', NULL),
    ('Biologia 1', 3, 'pu', 3.2),
    ('Biologia 1', 3, 'pe', NULL),
    ('Biologia 1', 3, 'ch', 2.3),
    ('Biologia 1', 4, 'pp', NULL),
    ('Biologia 1', 4, 'pu', NULL),
    ('Biologia 1', 4, 'pe', NULL),
    ('Biologia 1', 4, 'ch', NULL),
    ('Biologia 1', 81, 'rec', 5.0),
    ('Biologia 2', 1, 'pp', 4.0),
    ('Biologia 2', 1, 'pu', 2.7),
    ('Biologia 2', 1, 'pe', NULL),
    ('Biologia 2', 1, 'ch', NULL),
    ('Biologia 2', 2, 'pp', 3.0),
    ('Biologia 2', 2, 'pu', 3.1),
    ('Biologia 2', 2, 'pe', NULL),
    ('Biologia 2', 2, 'ch', NULL),
    ('Biologia 2', 3, 'pp', NULL),
    ('Biologia 2', 3, 'pu', 3.1),
    ('Biologia 2', 3, 'pe', NULL),
    ('Biologia 2', 3, 'ch', 2.3),
    ('Biologia 2', 4, 'pp', NULL),
    ('Biologia 2', 4, 'pu', NULL),
    ('Biologia 2', 4, 'pe', NULL),
    ('Biologia 2', 4, 'ch', NULL),
    ('Biologia 2', 81, 'rec', 5.0),
    ('Língua Inglesa', 1, 'pp', 6.0),
    ('Língua Inglesa', 1, 'pu', 7.5),
    ('Língua Inglesa', 1, 'pe', NULL),
    ('Língua Inglesa', 1, 'ch', NULL),
    ('Língua Inglesa', 2, 'pp', 4.5),
    ('Língua Inglesa', 2, 'pu', 8.8),
    ('Língua Inglesa', 2, 'pe', NULL),
    ('Língua Inglesa', 2, 'ch', NULL),
    ('Língua Inglesa', 3, 'pp', 8.0),
    ('Língua Inglesa', 3, 'pu', 7.1),
    ('Língua Inglesa', 3, 'pe', NULL),
    ('Língua Inglesa', 3, 'ch', NULL),
    ('Língua Inglesa', 4, 'pp', NULL),
    ('Língua Inglesa', 4, 'pu', NULL),
    ('Língua Inglesa', 4, 'pe', NULL),
    ('Língua Inglesa', 4, 'ch', NULL),
    ('Educação Física', 1, 'pp', 10.0),
    ('Educação Física', 1, 'pu', 7.5),
    ('Educação Física', 1, 'pe', NULL),
    ('Educação Física', 1, 'ch', NULL),
    ('Educação Física', 2, 'pp', 10.0),
    ('Educação Física', 2, 'pu', 8.8),
    ('Educação Física', 2, 'pe', NULL),
    ('Educação Física', 2, 'ch', NULL),
    ('Educação Física', 3, 'pp', 9.0),
    ('Educação Física', 3, 'pu', 7.1),
    ('Educação Física', 3, 'pe', NULL),
    ('Educação Física', 3, 'ch', NULL),
    ('Educação Física', 4, 'pp', 10.0),
    ('Educação Física', 4, 'pu', NULL),
    ('Educação Física', 4, 'pe', NULL),
    ('Educação Física', 4, 'ch', NULL);

  DROP TEMPORARY TABLE IF EXISTS sm24_tmp_fal;
  CREATE TEMPORARY TABLE sm24_tmp_fal (
    materia VARCHAR(80) NOT NULL,
    bim TINYINT NOT NULL,
    faltas INT NOT NULL,
    PRIMARY KEY (materia, bim)
  );
  INSERT INTO sm24_tmp_fal (materia, bim, faltas) VALUES
    ('Língua Portuguesa', 1, 0),
    ('Língua Portuguesa', 2, 0),
    ('Língua Portuguesa', 3, 2),
    ('Língua Portuguesa', 4, 0),
    ('Redação', 1, 0),
    ('Redação', 2, 0),
    ('Redação', 3, 2),
    ('Redação', 4, 0),
    ('Literatura', 1, 0),
    ('Literatura', 2, 0),
    ('Literatura', 3, 1),
    ('Literatura', 4, 0),
    ('Arte', 1, 0),
    ('Arte', 2, 0),
    ('Arte', 3, 1),
    ('Arte', 4, 0),
    ('História 1', 1, 0),
    ('História 1', 2, 0),
    ('História 1', 3, 2),
    ('História 1', 4, 0),
    ('História 2', 1, 0),
    ('História 2', 2, 0),
    ('História 2', 3, 4),
    ('História 2', 4, 0),
    ('Geografia', 1, 0),
    ('Geografia', 2, 0),
    ('Geografia', 3, 3),
    ('Geografia', 4, 0),
    ('Sociologia', 1, 0),
    ('Sociologia', 2, 0),
    ('Sociologia', 3, 2),
    ('Sociologia', 4, 0),
    ('Filosofia', 1, 0),
    ('Filosofia', 2, 0),
    ('Filosofia', 3, 2),
    ('Filosofia', 4, 0),
    ('Matemática 1', 1, 0),
    ('Matemática 1', 2, 0),
    ('Matemática 1', 3, 4),
    ('Matemática 1', 4, 0),
    ('Matemática 2', 1, 0),
    ('Matemática 2', 2, 0),
    ('Matemática 2', 3, 2),
    ('Matemática 2', 4, 0),
    ('Física 1', 1, 0),
    ('Física 1', 2, 0),
    ('Física 1', 3, 2),
    ('Física 1', 4, 0),
    ('Física 2', 1, 0),
    ('Física 2', 2, 0),
    ('Física 2', 3, 2),
    ('Física 2', 4, 0),
    ('Química 1', 1, 0),
    ('Química 1', 2, 0),
    ('Química 1', 3, 2),
    ('Química 1', 4, 0),
    ('Química 2', 1, 0),
    ('Química 2', 2, 0),
    ('Química 2', 3, 4),
    ('Química 2', 4, 0),
    ('Biologia 1', 1, 0),
    ('Biologia 1', 2, 0),
    ('Biologia 1', 3, 3),
    ('Biologia 1', 4, 0),
    ('Biologia 2', 1, 0),
    ('Biologia 2', 2, 0),
    ('Biologia 2', 3, 4),
    ('Biologia 2', 4, 0),
    ('Língua Inglesa', 1, 0),
    ('Língua Inglesa', 2, 0),
    ('Língua Inglesa', 3, 2),
    ('Língua Inglesa', 4, 0),
    ('Educação Física', 1, 0),
    ('Educação Física', 2, 0),
    ('Educação Física', 3, 1),
    ('Educação Física', 4, 0);

  SET v_bim = 1;
  WHILE v_bim <= 4 DO
    SET v_ev = 1;
    WHILE v_ev <= 4 DO
      -- CH (v_ev=3) só no 3º bimestre: no PDF a 2ª chamada aparece só ali.
      IF NOT (v_ev = 3 AND v_bim <> 3) THEN
        SET v_sigla = ELT(v_ev, 'PP', 'PU', 'CH', 'PE');
        SET v_nome_ev = CONCAT('SM24 ', ELT(v_ev, 'Prova Parcial', 'Prova de Unidade', '2ª Chamada', 'Ponto Extra'));
        SET v_tipo = ELT(v_ev, v_tipo_pp, v_tipo_pu, v_tipo_ch, v_tipo_pe);
        SET v_data = ELT(v_bim,
          ELT(v_ev, '2024-03-08','2024-04-12','2024-04-13','2024-04-15'),
          ELT(v_ev, '2024-05-17','2024-06-21','2024-06-22','2024-06-24'),
          ELT(v_ev, '2024-08-16','2024-09-20','2024-09-21','2024-09-23'),
          ELT(v_ev, '2024-10-18','2024-11-22','2024-11-23','2024-11-25')
        );

        INSERT INTO eventos_avaliativos (nome, sigla, descricao, ano_letivo, bimestre, tipo_avaliacao_id, ativo, liberar_nota_aluno, criado_por)
        VALUES (v_nome_ev, v_sigla, 'SM24', 2024, v_bim, v_tipo, 1, 1, v_admin);
        SET v_evento = LAST_INSERT_ID();

        INSERT INTO provas_blocos (
          titulo, descricao, data_prova, hora_inicio, hora_fim, criado_por, tipo_prova,
          formato_evento, configuracao_nota, ano_letivo, bimestre, tipo_avaliacao_id,
          evento_avaliativo_id, visivel_no_portal_aluno, ativo, liberado, status, turma_id
        ) VALUES (
          v_nome_ev, 'SM24', v_data, '07:10:00', '09:40:00', v_admin, 'original',
          'lancamento_nota', 'coordenacao_calcula', 2024, v_bim, v_tipo,
          v_evento, 1, 1, 1, 'liberado', v_turma
        );
        SET v_bloco = LAST_INSERT_ID();
        INSERT IGNORE INTO provas_blocos_turmas (bloco_id, turma_id) VALUES (v_bloco, v_turma);
        INSERT INTO provas_blocos_professores (bloco_id, professor_id, materia_id, quantidade_questoes)
        SELECT v_bloco, v_prof, m.id, 1
        FROM materias m
        WHERE m.codigo LIKE 'SM24-%' AND m.permite_avaliacao = 1;

        INSERT INTO provas_blocos_notas_lancadas (bloco_id, professor_id, materia_id, turma_id, aluno_id, nota)
        SELECT v_bloco, v_prof, m.id, v_turma, v_aluno, t.nota
        FROM sm24_tmp_notas t
        INNER JOIN materias m ON m.nome = t.materia AND m.codigo LIKE 'SM24-%'
        WHERE t.bim = v_bim AND t.tipo = LOWER(v_sigla) AND t.nota IS NOT NULL;
      END IF;

      SET v_ev = v_ev + 1;
    END WHILE;
    SET v_bim = v_bim + 1;
  END WHILE;

  -- R1 / R2 / RF
  INSERT INTO eventos_avaliativos (nome, sigla, descricao, ano_letivo, bimestre, tipo_avaliacao_id, ativo, liberar_nota_aluno, criado_por)
  VALUES
    ('SM24 Recuperação semestral', 'REC', 'SM24', 2024, 81, v_tipo_rec, 1, 1, v_admin),
    ('SM24 Recuperação 2º semestre', 'REC', 'SM24', 2024, 82, v_tipo_rec, 1, 1, v_admin),
    ('SM24 Prova Final', 'PF', 'SM24', 2024, 90, v_tipo_pf, 1, 1, v_admin);

  INSERT INTO provas_blocos (
    titulo, descricao, data_prova, hora_inicio, hora_fim, criado_por, tipo_prova,
    formato_evento, configuracao_nota, ano_letivo, bimestre, tipo_avaliacao_id,
    evento_avaliativo_id, visivel_no_portal_aluno, ativo, liberado, status, turma_id
  )
  SELECT e.nome, 'SM24',
         CASE e.bimestre WHEN 81 THEN '2024-07-08' WHEN 82 THEN '2024-12-09' ELSE '2024-12-16' END,
         '07:10:00', '09:40:00', v_admin, 'original',
         'lancamento_nota', 'coordenacao_calcula', 2024, e.bimestre, e.tipo_avaliacao_id,
         e.id, 1, 1, 1, 'liberado', v_turma
  FROM eventos_avaliativos e
  WHERE e.descricao = 'SM24' AND e.bimestre IN (81, 82, 90) AND e.deleted_at IS NULL;

  INSERT IGNORE INTO provas_blocos_turmas (bloco_id, turma_id)
  SELECT pb.id, v_turma
  FROM provas_blocos pb
  WHERE pb.descricao = 'SM24' AND pb.bimestre IN (81, 82, 90) AND pb.deleted_at IS NULL;

  INSERT INTO provas_blocos_professores (bloco_id, professor_id, materia_id, quantidade_questoes)
  SELECT pb.id, v_prof, m.id, 1
  FROM provas_blocos pb
  CROSS JOIN materias m
  WHERE pb.descricao = 'SM24' AND pb.bimestre IN (81, 82, 90) AND pb.deleted_at IS NULL
    AND m.codigo LIKE 'SM24-%' AND m.permite_avaliacao = 1;

  INSERT INTO provas_blocos_notas_lancadas (bloco_id, professor_id, materia_id, turma_id, aluno_id, nota)
  SELECT pb.id, v_prof, m.id, v_turma, v_aluno, t.nota
  FROM sm24_tmp_notas t
  INNER JOIN materias m ON m.nome = t.materia AND m.codigo LIKE 'SM24-%'
  INNER JOIN provas_blocos pb ON pb.descricao = 'SM24' AND pb.bimestre = 81 AND pb.deleted_at IS NULL
  WHERE t.bim = 81 AND t.tipo = 'rec' AND t.nota IS NOT NULL;

  INSERT INTO faltas_eventos (nome, bimestre, ano_letivo, turmas_json, origem, created_by, ativo)
  SELECT CONCAT('SM24 Faltas ', n, 'º bimestre 2024'), CAST(n AS CHAR), 2024, JSON_ARRAY(v_turma), 'manual', v_admin, 1
  FROM (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4) p;

  INSERT INTO faltas_lancamentos (evento_id, aluno_id, materia_id, faltas, created_by)
  SELECT e.id, v_aluno, m.id, f.faltas, v_admin
  FROM sm24_tmp_fal f
  INNER JOIN materias m ON m.nome = f.materia AND m.codigo LIKE 'SM24-%'
  INNER JOIN faltas_eventos e ON e.nome = CONCAT('SM24 Faltas ', f.bim, 'º bimestre 2024')
  WHERE f.faltas > 0;

  DROP TEMPORARY TABLE IF EXISTS sm24_tmp_notas;
  DROP TEMPORARY TABLE IF EXISTS sm24_tmp_fal;
END$$

DELIMITER ;

CALL seed_santa_maria_ana_2024();
DROP PROCEDURE IF EXISTS seed_santa_maria_ana_2024;
DROP PROCEDURE IF EXISTS apagar_santa_maria_ana_2024;