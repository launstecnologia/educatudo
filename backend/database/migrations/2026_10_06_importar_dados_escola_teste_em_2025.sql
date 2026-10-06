-- =============================================================================
-- Seed Escola Educa Teste — Ensino Médio 2025, divisão trimestral.
-- Senha (alunos, pais e professores): Teste@123
-- Prefixo exclusivo: alunos et25.* | professores ET25-* | turmas observacoes ET25
--
-- Master → Migrations → Escola teste → Escolher → marque SÓ este arquivo.
-- Não entra em "Executar todas" nem no bootstrap (nome importar_dados_*).
-- Pode rodar de novo: apaga o próprio seed e recria.
-- Para só esvaziar: 2026_10_06_importar_dados_escola_teste_em_2025_apagar.sql
-- Rode antes as migrations de schema dessa escola.
-- Volume alto (diário do ano + chamada). Se o navegador estourar o tempo,
-- execute este arquivo com o cliente mysql no banco da escola teste.
-- =============================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '-03:00';

DROP PROCEDURE IF EXISTS apagar_escola_teste_em_2025;
DROP PROCEDURE IF EXISTS seed_escola_teste_em_2025;

DELIMITER $$

-- BEGIN APAGAR
CREATE PROCEDURE apagar_escola_teste_em_2025()
BEGIN
  DROP TABLE IF EXISTS et25_tmp_media;
  DROP TABLE IF EXISTS et25_tmp_freq;

  DELETE FROM presenca_eventos WHERE id_externo LIKE 'et25-%';

  DELETE h FROM ocorrencias_historico h
  INNER JOIN alunos_ocorrencias o ON o.id = h.ocorrencia_id
  WHERE o.titulo LIKE 'ET25 %';

  DELETE i FROM alunos_ocorrencias_itens i
  INNER JOIN alunos_ocorrencias o ON o.id = i.ocorrencia_id
  WHERE o.titulo LIKE 'ET25 %';

  DELETE FROM alunos_ocorrencias WHERE titulo LIKE 'ET25 %';

  DELETE l FROM faltas_lancamentos l
  INNER JOIN faltas_eventos e ON e.id = l.evento_id
  WHERE e.nome LIKE 'ET25 %';

  DELETE FROM faltas_eventos WHERE nome LIKE 'ET25 %';

  UPDATE diario_aulas
  SET plano_aula_id = NULL, evento_bloco_id = NULL
  WHERE observacoes LIKE 'ET25-SEED%';

  DELETE FROM diario_aulas WHERE observacoes LIKE 'ET25-SEED%';

  DELETE FROM planos_aula WHERE titulo LIKE 'ET25 %';

  DELETE FROM boletim_regras WHERE codigo LIKE 'et25-%';

  DELETE n FROM notas_tipo_finais n
  INNER JOIN alunos a ON a.id = n.aluno_id
  WHERE a.nickname LIKE 'et25.%';

  DELETE n FROM provas_blocos_notas_lancadas n
  INNER JOIN provas_blocos pb ON pb.id = n.bloco_id
  WHERE pb.titulo LIKE 'ET25 %';

  DELETE FROM provas_blocos WHERE titulo LIKE 'ET25 %';

  DELETE e FROM resultado_documento_emissoes e
  INNER JOIN alunos a ON a.id = e.aluno_id
  WHERE a.nickname LIKE 'et25.%' OR e.hash_validacao LIKE 'et25%';

  DELETE FROM resultado_academico
  WHERE aluno_id IN (SELECT id FROM alunos WHERE nickname LIKE 'et25.%');

  DELETE h FROM fechamento_periodo_historico h
  INNER JOIN fechamento_periodo f ON f.id = h.fechamento_id
  WHERE f.vigente_chave LIKE 'ET25-%' OR f.periodo_ref LIKE 'ET25-%';

  DELETE FROM fechamento_periodo
  WHERE vigente_chave LIKE 'ET25-%' OR periodo_ref LIKE 'ET25-%';

  DELETE FROM historico_documentos
  WHERE aluno_id IN (SELECT id FROM alunos WHERE nickname LIKE 'et25.%')
     OR numero_registro_sed LIKE 'ET25-%';

  DELETE FROM matricula_transferencias WHERE protocolo LIKE 'ET25-%';

  DELETE d FROM alunos_documentos d
  INNER JOIN alunos a ON a.id = d.aluno_id
  WHERE a.nickname LIKE 'et25.%';

  DELETE f FROM alunos_ficha_complementar f
  INNER JOIN alunos a ON a.id = f.aluno_id
  WHERE a.nickname LIKE 'et25.%';

  DELETE s FROM alunos_historico_status s
  INNER JOIN alunos a ON a.id = s.student_id
  WHERE a.nickname LIKE 'et25.%';

  UPDATE alunos SET responsavel_id = NULL WHERE nickname LIKE 'et25.%';

  DELETE FROM matricula
  WHERE aluno_id IN (SELECT id FROM alunos WHERE nickname LIKE 'et25.%');

  DELETE FROM alunos WHERE nickname LIKE 'et25.%';

  DELETE FROM responsaveis
  WHERE email LIKE 'mae.et25.%@educateste.local'
     OR email LIKE 'pai.et25.%@educateste.local';

  DELETE gh FROM grade_horaria gh
  INNER JOIN turmas t ON t.id = gh.turma_id
  WHERE t.observacoes LIKE 'ET25 %';

  DELETE FROM turmas WHERE observacoes LIKE 'ET25 %';

  DELETE d FROM professores_documentos d
  INNER JOIN professores p ON p.id = d.professor_id
  WHERE p.codigo_prof LIKE 'ET25-%';

  DELETE FROM professores WHERE codigo_prof LIKE 'ET25-%';

  DELETE c FROM matrizes_curriculares_componentes c
  INNER JOIN matrizes_curriculares m ON m.id = c.matriz_id
  WHERE m.codigo LIKE 'ET25-EM%';

  DELETE FROM matrizes_curriculares WHERE codigo LIKE 'ET25-EM%';

  DELETE FROM school_locations WHERE codigo LIKE 'ET25-%';

  DELETE FROM regras_academicas WHERE codigo = 'et25-em-2025';

  DELETE v FROM calendario_letivo_vinculos v
  INNER JOIN calendario_letivo c ON c.id = v.calendario_id
  WHERE c.observacao = 'ET25 Calendário Ensino Médio 2025';

  DELETE e FROM calendario_letivo_eventos e
  INNER JOIN calendario_letivo c ON c.id = e.calendario_id
  WHERE c.observacao = 'ET25 Calendário Ensino Médio 2025';

  DELETE FROM calendario_letivo WHERE observacao = 'ET25 Calendário Ensino Médio 2025';

  DELETE FROM ano_letivo
  WHERE ano = 2025
    AND NOT EXISTS (SELECT 1 FROM turmas WHERE ano_letivo = 2025);
END$$
-- END APAGAR

CREATE PROCEDURE seed_escola_teste_em_2025()
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
  DECLARE v_tipo_s1 INT DEFAULT 0;
  DECLARE v_tipo_s2 INT DEFAULT 0;
  DECLARE v_tipo_s3 INT DEFAULT 0;
  DECLARE v_tipo_pb INT DEFAULT 0;
  DECLARE v_tipo_rec INT DEFAULT 0;
  DECLARE v_regra_acad INT DEFAULT 0;
  DECLARE v_t INT DEFAULT 0;
  DECLARE v_n INT DEFAULT 0;
  DECLARE v_c INT DEFAULT 0;
  DECLARE v_idx INT DEFAULT 0;
  DECLARE v_livre INT DEFAULT 0;
  DECLARE v_subj INT DEFAULT 0;
  DECLARE v_dia INT DEFAULT 0;
  DECLARE v_per INT DEFAULT 0;
  DECLARE v_slot INT DEFAULT 0;
  DECLARE v_turma_id INT DEFAULT 0;
  DECLARE v_mat_id INT DEFAULT 0;
  DECLARE v_prof_id INT DEFAULT 0;
  DECLARE v_aluno_id INT DEFAULT 0;
  DECLARE v_mae_id INT DEFAULT 0;
  DECLARE v_pai_id INT DEFAULT 0;
  DECLARE v_bim INT DEFAULT 0;
  DECLARE v_ev INT DEFAULT 0;
  DECLARE v_bloco_id INT DEFAULT 0;
  DECLARE v_regra INT DEFAULT 0;
  DECLARE v_cat_falta INT DEFAULT 0;
  DECLARE v_cat_atraso INT DEFAULT 0;
  DECLARE v_cat_saida INT DEFAULT 0;
  DECLARE v_cat_elogio INT DEFAULT 0;
  DECLARE v_nick VARCHAR(50);
  DECLARE v_nome VARCHAR(255);
  DECLARE v_nome_mae VARCHAR(255);
  DECLARE v_nome_pai VARCHAR(255);
  DECLARE v_serie_nome VARCHAR(50);
  DECLARE v_serie_cod CHAR(1);
  DECLARE v_letra CHAR(1);
  DECLARE v_turma_nome VARCHAR(20);
  DECLARE v_de TIME;
  DECLARE v_ate TIME;
  DECLARE v_entrada DATE;
  DECLARE v_saida DATE;
  DECLARE v_titulo VARCHAR(255);
  DECLARE v_data_ev DATE;
  DECLARE v_mats JSON;

  CALL apagar_escola_teste_em_2025();

  INSERT INTO usuarios (tipo, perfil_admin, nome, email, senha_hash, ativo)
  SELECT 'admin_escola', 'dev', 'Admin Escola Educa Teste', 'admin@educateste.local', v_hash, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM usuarios WHERE email = 'admin@educateste.local');
  SET v_admin = (SELECT id FROM usuarios WHERE email = 'admin@educateste.local' LIMIT 1);
  IF v_admin IS NULL OR v_admin = 0 THEN
    SET v_admin = (SELECT id FROM usuarios WHERE tipo = 'admin_escola' ORDER BY id LIMIT 1);
  END IF;
  IF v_admin IS NULL OR v_admin = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Nenhum usuário admin da escola para assinar o seed ET25.';
  END IF;

  INSERT INTO unidades (
    nome, tipo, razao_social, cnpj, inep, dependencia_administrativa,
    endereco, numero, complemento, bairro, cidade, uf, cep, telefone, email,
    diretor_nome, secretario_nome, ato_autorizacao, ato_credenciamento, ato_reconhecimento,
    diretor_registro, secretario_registro, ativo
  )
  SELECT
    'Escola Educa Teste', 'matriz', 'Escola Educa Teste Ltda', '11.222.333/0001-81', '35987654', 'privada',
    'Rua das Palmeiras', '100', 'Bloco A', 'Centro', 'São Paulo', 'SP', '01310-100',
    '(11) 3000-2025', 'contato@educateste.local',
    'Helena Duarte', 'Renata Alves',
    'Portaria CEE/SP nº 2025/01', 'Parecer CEE/SP nº 2025/02', 'Resolução CEE/SP nº 2025/03',
    'RG 12.345.678-9 SSP/SP', 'RG 98.765.432-1 SSP/SP', 1
  FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM unidades WHERE nome = 'Escola Educa Teste' OR cnpj = '11.222.333/0001-81');
  SET v_unidade = (SELECT id FROM unidades WHERE nome = 'Escola Educa Teste' LIMIT 1);

  INSERT INTO ano_letivo (ano, data_inicio, data_fim, periodo_tipo, ativo)
  SELECT 2025, '2025-02-03', '2025-12-12', 'trimestre', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM ano_letivo WHERE ano = 2025);
  UPDATE ano_letivo
  SET data_inicio = '2025-02-03', data_fim = '2025-12-12', periodo_tipo = 'trimestre', ativo = 1
  WHERE ano = 2025;
  SET v_ano_id = (SELECT id FROM ano_letivo WHERE ano = 2025 LIMIT 1);

  INSERT INTO calendario_letivo (ano, nome, dias_meta, carga_horaria_meta, observacao)
  SELECT 2025, 'Ensino Médio 2025', 200, 800, 'ET25 Calendário Ensino Médio 2025' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM calendario_letivo WHERE observacao = 'ET25 Calendário Ensino Médio 2025');
  SET v_cal = (SELECT id FROM calendario_letivo WHERE observacao = 'ET25 Calendário Ensino Médio 2025' LIMIT 1);

  INSERT INTO calendario_letivo_eventos (calendario_id, data_inicio, data_fim, tipo, descricao, visivel_aluno, visivel_professor, visivel_pais)
  SELECT v_cal, d.i, d.f, d.t, d.n, 1, 1, 1
  FROM (
    SELECT '2025-02-03' i, '2025-02-03' f, 'evento' t, 'ET25 Início do ano letivo' n
    UNION ALL SELECT '2025-03-03', '2025-03-05', 'recesso', 'ET25 Carnaval'
    UNION ALL SELECT '2025-04-18', '2025-04-18', 'feriado', 'ET25 Paixão de Cristo'
    UNION ALL SELECT '2025-04-21', '2025-04-21', 'feriado', 'ET25 Tiradentes'
    UNION ALL SELECT '2025-05-01', '2025-05-01', 'feriado', 'ET25 Dia do Trabalho'
    UNION ALL SELECT '2025-05-05', '2025-05-09', 'avaliacao', 'ET25 Semana de provas 1º trimestre'
    UNION ALL SELECT '2025-05-15', '2025-05-15', 'avaliacao', 'ET25 Recuperação 1º trimestre'
    UNION ALL SELECT '2025-05-16', '2025-05-16', 'evento', 'ET25 Conselho de classe 1º trimestre'
    UNION ALL SELECT '2025-06-19', '2025-06-19', 'feriado', 'ET25 Corpus Christi'
    UNION ALL SELECT '2025-07-14', '2025-07-25', 'recesso', 'ET25 Recesso de julho'
    UNION ALL SELECT '2025-08-11', '2025-08-15', 'avaliacao', 'ET25 Semana de provas 2º trimestre'
    UNION ALL SELECT '2025-08-21', '2025-08-21', 'avaliacao', 'ET25 Recuperação 2º trimestre'
    UNION ALL SELECT '2025-08-29', '2025-08-29', 'evento', 'ET25 Conselho de classe 2º trimestre'
    UNION ALL SELECT '2025-09-07', '2025-09-07', 'feriado', 'ET25 Independência'
    UNION ALL SELECT '2025-10-12', '2025-10-12', 'feriado', 'ET25 Nossa Senhora Aparecida'
    UNION ALL SELECT '2025-11-02', '2025-11-02', 'feriado', 'ET25 Finados'
    UNION ALL SELECT '2025-11-15', '2025-11-15', 'feriado', 'ET25 Proclamação da República'
    UNION ALL SELECT '2025-11-17', '2025-11-21', 'avaliacao', 'ET25 Semana de provas 3º trimestre'
    UNION ALL SELECT '2025-11-20', '2025-11-20', 'feriado', 'ET25 Consciência Negra'
    UNION ALL SELECT '2025-11-27', '2025-11-27', 'avaliacao', 'ET25 Recuperação 3º trimestre'
    UNION ALL SELECT '2025-12-12', '2025-12-12', 'evento', 'ET25 Conselho de classe final'
    UNION ALL SELECT '2025-12-13', '2025-12-31', 'recesso', 'ET25 Encerramento / férias'
  ) d;

  INSERT INTO curso (nome, tipo, possui_serie, descricao, ativo, ordem)
  SELECT 'Ensino Médio', 'regular', 1, 'Ensino Médio Regular — Escola Educa Teste', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM curso WHERE nome = 'Ensino Médio');
  SET v_curso = (SELECT id FROM curso WHERE nome = 'Ensino Médio' LIMIT 1);

  INSERT INTO calendario_letivo_vinculos (calendario_id, curso_id, serie_id)
  SELECT v_cal, v_curso, NULL FROM DUAL
  WHERE NOT EXISTS (
    SELECT 1 FROM calendario_letivo_vinculos
    WHERE calendario_id = v_cal AND curso_id = v_curso AND serie_id IS NULL
  );

  INSERT INTO serie (curso_id, nome, ordem, ativo)
  SELECT v_curso, '1º Ano EM', 1, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM serie WHERE curso_id = v_curso AND nome = '1º Ano EM');
  INSERT INTO serie (curso_id, nome, ordem, ativo)
  SELECT v_curso, '2º Ano EM', 2, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM serie WHERE curso_id = v_curso AND nome = '2º Ano EM');
  INSERT INTO serie (curso_id, nome, ordem, ativo)
  SELECT v_curso, '3º Ano EM', 3, 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM serie WHERE curso_id = v_curso AND nome = '3º Ano EM');
  SET v_s1 = (SELECT id FROM serie WHERE curso_id = v_curso AND nome = '1º Ano EM' LIMIT 1);
  SET v_s2 = (SELECT id FROM serie WHERE curso_id = v_curso AND nome = '2º Ano EM' LIMIT 1);
  SET v_s3 = (SELECT id FROM serie WHERE curso_id = v_curso AND nome = '3º Ano EM' LIMIT 1);

  IF (SELECT COUNT(*) FROM materias WHERE nome IN (
        'Língua Portuguesa','Matemática','História','Geografia','Física','Química','Biologia','Sociologia'
      )) < 8 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Catálogo de componentes incompleto. Rode as migrations de schema antes.';
  END IF;

  INSERT INTO matrizes_curriculares (nome, codigo, curso_id, serie_id, modalidade, turno, carga_horaria_anual_prevista, dias_letivos_previstos, duracao_padrao_aula_minutos, base_legal, observacoes, ativo)
  SELECT 'Matriz 1º Ano EM 2025 — manhã', 'ET25-EM1', v_curso, v_s1, 'presencial', 'manha', 640, 200, 50, 'BNCC / LDB 9.394/96', 'ET25 formação geral básica, 8 componentes, 2 aulas/semana', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM matrizes_curriculares WHERE codigo = 'ET25-EM1');
  INSERT INTO matrizes_curriculares (nome, codigo, curso_id, serie_id, modalidade, turno, carga_horaria_anual_prevista, dias_letivos_previstos, duracao_padrao_aula_minutos, base_legal, observacoes, ativo)
  SELECT 'Matriz 2º Ano EM 2025 — manhã', 'ET25-EM2', v_curso, v_s2, 'presencial', 'manha', 640, 200, 50, 'BNCC / LDB 9.394/96', 'ET25 formação geral básica, 8 componentes, 2 aulas/semana', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM matrizes_curriculares WHERE codigo = 'ET25-EM2');
  INSERT INTO matrizes_curriculares (nome, codigo, curso_id, serie_id, modalidade, turno, carga_horaria_anual_prevista, dias_letivos_previstos, duracao_padrao_aula_minutos, base_legal, observacoes, ativo)
  SELECT 'Matriz 3º Ano EM 2025 — manhã', 'ET25-EM3', v_curso, v_s3, 'presencial', 'manha', 640, 200, 50, 'BNCC / LDB 9.394/96', 'ET25 formação geral básica, 8 componentes, 2 aulas/semana', 1 FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM matrizes_curriculares WHERE codigo = 'ET25-EM3');
  SET v_mx1 = (SELECT id FROM matrizes_curriculares WHERE codigo = 'ET25-EM1' LIMIT 1);
  SET v_mx2 = (SELECT id FROM matrizes_curriculares WHERE codigo = 'ET25-EM2' LIMIT 1);
  SET v_mx3 = (SELECT id FROM matrizes_curriculares WHERE codigo = 'ET25-EM3' LIMIT 1);

  INSERT IGNORE INTO matrizes_curriculares_componentes (matriz_id, materia_id, aulas_semana, obrigatorio, ordem_boletim, ordem_historico)
  SELECT mx.id, m.id, 2, 1, c.ord, c.ord
  FROM (
    SELECT 'Língua Portuguesa' n, 1 ord UNION ALL SELECT 'Matemática', 2
    UNION ALL SELECT 'História', 3 UNION ALL SELECT 'Geografia', 4
    UNION ALL SELECT 'Física', 5 UNION ALL SELECT 'Química', 6
    UNION ALL SELECT 'Biologia', 7 UNION ALL SELECT 'Sociologia', 8
  ) c
  INNER JOIN materias m ON m.nome = c.n
  INNER JOIN matrizes_curriculares mx ON mx.codigo IN ('ET25-EM1','ET25-EM2','ET25-EM3');

  SET v_t = 1;
  WHILE v_t <= 12 DO
    INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, responsavel_nome, ativo)
    VALUES (
      CONCAT('ET25-SALA-', LPAD(v_t, 2, '0')),
      CONCAT('Sala ', LPAD(v_t, 2, '0'), ' — manhã'),
      'sala', 50, IF(v_t <= 6, 'A', 'B'), '1', 'Coordenação', 1
    );
    SET v_t = v_t + 1;
  END WHILE;
  INSERT INTO school_locations (codigo, nome, tipo, capacidade, bloco, andar, responsavel_nome, ativo) VALUES
    ('ET25-LAB-01', 'Laboratório de Ciências', 'laboratorio', 40, 'C', '1', 'Coordenação de Ciências', 1),
    ('ET25-LAB-02', 'Laboratório de Informática', 'laboratorio', 40, 'C', '2', 'Coordenação de Ciências', 1),
    ('ET25-BIB-01', 'Biblioteca', 'biblioteca', 60, 'A', 'Térreo', 'Biblioteca', 1),
    ('ET25-QUA-01', 'Quadra poliesportiva', 'quadra', 80, 'Externo', 'Térreo', 'Educação Física', 1),
    ('ET25-AUD-01', 'Auditório', 'outro', 120, 'A', 'Térreo', 'Direção', 1),
    ('ET25-SEC-01', 'Secretaria', 'secretaria', 10, 'A', 'Térreo', 'Renata Alves', 1);

  INSERT INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem, chave_quadro)
  SELECT 'Simulado 1', 'ET25 primeiro simulado do trimestre.', 1, 11, 'et25_sim1' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM provas_tipos_avaliacao WHERE nome = 'Simulado 1' AND deleted_at IS NULL);
  INSERT INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem, chave_quadro)
  SELECT 'Simulado 2', 'ET25 segundo simulado do trimestre.', 1, 12, 'et25_sim2' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM provas_tipos_avaliacao WHERE nome = 'Simulado 2' AND deleted_at IS NULL);
  INSERT INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem, chave_quadro)
  SELECT 'Simulado 3', 'ET25 terceiro simulado do trimestre.', 1, 13, 'et25_sim3' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM provas_tipos_avaliacao WHERE nome = 'Simulado 3' AND deleted_at IS NULL);
  INSERT INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem, chave_quadro)
  SELECT 'Prova Bimestral', 'ET25 prova principal do trimestre (nome legado do quadro).', 1, 20, 'prova_bim' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM provas_tipos_avaliacao WHERE nome = 'Prova Bimestral' AND deleted_at IS NULL);
  INSERT INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem, chave_quadro)
  SELECT 'Rec', 'ET25 recuperação do trimestre. Substitui a prova só até a nota 6,00.', 1, 30, 'et25_rec' FROM DUAL
  WHERE NOT EXISTS (SELECT 1 FROM provas_tipos_avaliacao WHERE nome = 'Rec' AND deleted_at IS NULL);
  SET v_tipo_s1 = (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'Simulado 1' AND deleted_at IS NULL LIMIT 1);
  SET v_tipo_s2 = (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'Simulado 2' AND deleted_at IS NULL LIMIT 1);
  SET v_tipo_s3 = (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'Simulado 3' AND deleted_at IS NULL LIMIT 1);
  SET v_tipo_pb = (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'Prova Bimestral' AND deleted_at IS NULL LIMIT 1);
  SET v_tipo_rec = (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'Rec' AND deleted_at IS NULL LIMIT 1);

  INSERT INTO regras_academicas (
    nome, codigo, ano_letivo, curso_id, periodo_tipo, media_minima, frequencia_minima,
    usar_frequencia, recuperacao_tipo, recuperacao_composicao, formula_final, round_mode, decimal_places, ativo, observacoes
  ) VALUES (
    'Regra EM 2025 — trimestral', 'et25-em-2025', 2025, v_curso, 'trimestre', 6.00, 75.00,
    1, 'periodo', 'formula', 'max(media, min(rec, 6))', 'none', 2, 1,
    'ET25 Aprovação com média 6,00 e 75% de frequência. Recuperação por trimestre, limitada à nota mínima.'
  );
  SET v_regra_acad = (SELECT id FROM regras_academicas WHERE codigo = 'et25-em-2025' LIMIT 1);

  INSERT INTO professores (nome, email, senha_hash, codigo_prof, materias, turmas, ativo, pagante, password)
  SELECT d.nome, d.email, v_hash, d.cod, JSON_ARRAY(), JSON_ARRAY(), 1, 0, ''
  FROM (
    SELECT 'Lúcia Prado Ferreira' nome, 'portugues.2025@educateste.local' email, 'ET25-LPO' cod
    UNION ALL SELECT 'Marcos Tavares Lima', 'matematica.2025@educateste.local', 'ET25-MAT'
    UNION ALL SELECT 'Hugo Sampaio Dias', 'historia.2025@educateste.local', 'ET25-HIS'
    UNION ALL SELECT 'Gisele Ramos Nunes', 'geografia.2025@educateste.local', 'ET25-GEO'
    UNION ALL SELECT 'Fábio Nunes Carvalho', 'fisica.2025@educateste.local', 'ET25-FIS'
    UNION ALL SELECT 'Carla Menezes Rocha', 'quimica.2025@educateste.local', 'ET25-QUI'
    UNION ALL SELECT 'Beatriz Cunha Alves', 'biologia.2025@educateste.local', 'ET25-BIO'
    UNION ALL SELECT 'Sérgio Pacheco Martins', 'sociologia.2025@educateste.local', 'ET25-SOC'
  ) d;

  UPDATE professores p
  INNER JOIN (
    SELECT 'ET25-LPO' cod, 'Língua Portuguesa' mat
    UNION ALL SELECT 'ET25-MAT', 'Matemática'
    UNION ALL SELECT 'ET25-HIS', 'História'
    UNION ALL SELECT 'ET25-GEO', 'Geografia'
    UNION ALL SELECT 'ET25-FIS', 'Física'
    UNION ALL SELECT 'ET25-QUI', 'Química'
    UNION ALL SELECT 'ET25-BIO', 'Biologia'
    UNION ALL SELECT 'ET25-SOC', 'Sociologia'
  ) d ON d.cod = p.codigo_prof
  INNER JOIN materias m ON m.nome = d.mat
  SET p.materias = JSON_ARRAY(m.id);

  INSERT INTO professores_documentos (professor_id, tipo, status, titulo, observacao, entregue_em)
  SELECT p.id, d.tipo, 'entregue', d.titulo, 'ET25 cadastro completo', NOW()
  FROM professores p
  CROSS JOIN (
    SELECT 'rg' tipo, 'RG' titulo
    UNION ALL SELECT 'cpf', 'CPF'
    UNION ALL SELECT 'diploma', 'Diploma de licenciatura'
    UNION ALL SELECT 'contrato', 'Contrato de trabalho'
    UNION ALL SELECT 'comprovante_residencia', 'Comprovante de residência'
  ) d
  WHERE p.codigo_prof LIKE 'ET25-%';

  SET v_t = 1;
  WHILE v_t <= 12 DO
    SET v_serie_cod = ELT(v_t, '1','1','1','1','2','2','2','2','3','3','3','3');
    SET v_letra = ELT(v_t, 'A','B','C','D','A','B','C','D','A','B','C','D');
    SET v_serie_nome = ELT(v_t, '1º Ano EM','1º Ano EM','1º Ano EM','1º Ano EM','2º Ano EM','2º Ano EM','2º Ano EM','2º Ano EM','3º Ano EM','3º Ano EM','3º Ano EM','3º Ano EM');
    SET v_turma_nome = CONCAT(v_serie_cod, 'º ', v_letra);
    INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, matriz_curricular_id, curso_novo_id, ativo, tipo_ensino, vagas, turno, sala_padrao_id, observacoes)
    VALUES (
      v_turma_nome, 2025, v_ano_id, v_serie_nome,
      ELT(v_t, v_s1,v_s1,v_s1,v_s1,v_s2,v_s2,v_s2,v_s2,v_s3,v_s3,v_s3,v_s3),
      ELT(v_t, v_mx1,v_mx1,v_mx1,v_mx1,v_mx2,v_mx2,v_mx2,v_mx2,v_mx3,v_mx3,v_mx3,v_mx3),
      v_curso, 1, 'medio', 50, 'manha',
      (SELECT id FROM school_locations WHERE codigo = CONCAT('ET25-SALA-', LPAD(v_t, 2, '0')) LIMIT 1),
      CONCAT('ET25 ', v_turma_nome)
    );
    SET v_t = v_t + 1;
  END WHILE;

  UPDATE professores
  SET turmas = (SELECT JSON_ARRAYAGG(id) FROM turmas WHERE observacoes LIKE 'ET25 %')
  WHERE codigo_prof LIKE 'ET25-%';

  SET v_n = 0;
  WHILE v_n < 24 DO
    SET v_livre = v_n MOD 3;
    SET v_dia = 1 + (v_n DIV 5);
    SET v_per = (v_n MOD 5) + 1;
    SET v_de = ELT(v_per, '07:10:00','08:00:00','08:50:00','10:00:00','10:50:00');
    SET v_ate = ELT(v_per, '08:00:00','08:50:00','09:40:00','10:50:00','11:40:00');
    SET v_idx = 0;
    SET v_c = 0;
    WHILE v_c < 12 DO
      IF (v_c DIV 4) <> v_livre THEN
        SET v_subj = (v_idx + (v_n DIV 3)) MOD 8;
        SET v_serie_cod = ELT(v_c + 1, '1','1','1','1','2','2','2','2','3','3','3','3');
        SET v_letra = ELT(v_c + 1, 'A','B','C','D','A','B','C','D','A','B','C','D');
        SET v_turma_id = (SELECT id FROM turmas WHERE ano_letivo = 2025 AND nome = CONCAT(v_serie_cod, 'º ', v_letra) AND observacoes LIKE 'ET25 %' LIMIT 1);
        SET v_mat_id = (SELECT id FROM materias WHERE nome = ELT(v_subj + 1, 'Língua Portuguesa','Matemática','História','Geografia','Física','Química','Biologia','Sociologia') LIMIT 1);
        SET v_prof_id = (SELECT id FROM professores WHERE codigo_prof = ELT(v_subj + 1, 'ET25-LPO','ET25-MAT','ET25-HIS','ET25-GEO','ET25-FIS','ET25-QUI','ET25-BIO','ET25-SOC') LIMIT 1);
        INSERT INTO grade_horaria (turma_id, materia_id, professor_id, dia_semana, horario_de, horario_ate, periodo)
        VALUES (v_turma_id, v_mat_id, v_prof_id, v_dia, v_de, v_ate, 'manha');
        SET v_idx = v_idx + 1;
      END IF;
      SET v_c = v_c + 1;
    END WHILE;
    SET v_n = v_n + 1;
  END WHILE;

  IF (SELECT COUNT(*) FROM grade_horaria gh INNER JOIN turmas t ON t.id = gh.turma_id WHERE t.observacoes LIKE 'ET25 %') <> 192 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Grade ET25 não fechou 192 aulas sem conflito.';
  END IF;

  SET v_t = 1;
  WHILE v_t <= 12 DO
    SET v_serie_cod = ELT(v_t, '1','1','1','1','2','2','2','2','3','3','3','3');
    SET v_letra = ELT(v_t, 'A','B','C','D','A','B','C','D','A','B','C','D');
    SET v_serie_nome = ELT(v_t, '1º Ano EM','1º Ano EM','1º Ano EM','1º Ano EM','2º Ano EM','2º Ano EM','2º Ano EM','2º Ano EM','3º Ano EM','3º Ano EM','3º Ano EM','3º Ano EM');
    SET v_turma_nome = CONCAT(v_serie_cod, 'º ', v_letra);
    SET v_turma_id = (SELECT id FROM turmas WHERE ano_letivo = 2025 AND nome = v_turma_nome AND observacoes LIKE 'ET25 %' LIMIT 1);
    SET v_slot = 1;
    WHILE v_slot <= 50 DO
      SET v_nick = CONCAT('et25.', v_serie_cod, LOWER(v_letra), LPAD(v_slot, 2, '0'));
      SET v_nome = CONCAT(
        IF(v_slot MOD 2 = 0,
          ELT(1 + (((v_t - 1) * 50 + (v_slot - 1)) MOD 36), 'Ana','Beatriz','Camila','Daniela','Eduarda','Fernanda','Gabriela','Helena','Isabela','Juliana','Larissa','Mariana','Natália','Patrícia','Rafaela','Sabrina','Tatiane','Vanessa','Yasmin','Alice','Bianca','Carolina','Débora','Elisa','Flávia','Giovana','Heloísa','Ingrid','Jéssica','Letícia','Melissa','Nicole','Renata','Sofia','Taís','Vitória'),
          ELT(1 + (((v_t - 1) * 50 + (v_slot - 1)) MOD 36), 'André','Bruno','Carlos','Diego','Eduardo','Felipe','Gabriel','Henrique','Igor','João','Kaique','Lucas','Marcos','Nicolas','Otávio','Pedro','Rafael','Samuel','Thiago','Vinícius','Antônio','Bernardo','Caio','Daniel','Enzo','Fernando','Gustavo','Heitor','Isaac','José','Leandro','Matheus','Nathan','Paulo','Ricardo','Rodrigo')
        ),
        ' ',
        IF(v_slot MOD 2 = 0,
          ELT(1 + ((((v_t - 1) * 50 + (v_slot - 1)) DIV 7) MOD 20), 'Clara','Cristina','Luiza','Aparecida','Regina','Vitória','Beatriz','Helena','Fernanda','Cecília','Lorena','Maitê','Pietra','Valentina','Alice','Lívia','Maya','Lara','Isis','Aurora'),
          ELT(1 + ((((v_t - 1) * 50 + (v_slot - 1)) DIV 7) MOD 20), 'Miguel','Henrique','Eduardo','Antônio','Gabriel','Luiz','Fernando','Augusto','Roberto','Paulo','Vinícius','Heitor','Davi','Benício','Theo','Arthur','Samuel','Isaac','Caio','Enzo')
        ),
        ' ',
        ELT(1 + (((v_t - 1) * 50 + (v_slot - 1)) MOD 40), 'Silva','Santos','Oliveira','Souza','Lima','Pereira','Ferreira','Almeida','Costa','Rodrigues','Martins','Araújo','Barbosa','Cardoso','Correia','Dias','Fernandes','Gomes','Lopes','Mendes','Nascimento','Nunes','Pinto','Ramos','Ribeiro','Rocha','Teixeira','Carvalho','Castro','Monteiro','Moreira','Freitas','Azevedo','Cunha','Duarte','Farias','Melo','Moura','Rezende','Vieira'),
        ' ',
        ELT(1 + (IF(
          ELT(1 + (((v_t - 1) * 50 + (v_slot - 1)) MOD 40), 'Silva','Santos','Oliveira','Souza','Lima','Pereira','Ferreira','Almeida','Costa','Rodrigues','Martins','Araújo','Barbosa','Cardoso','Correia','Dias','Fernandes','Gomes','Lopes','Mendes','Nascimento','Nunes','Pinto','Ramos','Ribeiro','Rocha','Teixeira','Carvalho','Castro','Monteiro','Moreira','Freitas','Azevedo','Cunha','Duarte','Farias','Melo','Moura','Rezende','Vieira') = ELT(1 + ((((v_t - 1) * 50 + (v_slot - 1)) * 3 + 11) MOD 40), 'Silva','Santos','Oliveira','Souza','Lima','Pereira','Ferreira','Almeida','Costa','Rodrigues','Martins','Araújo','Barbosa','Cardoso','Correia','Dias','Fernandes','Gomes','Lopes','Mendes','Nascimento','Nunes','Pinto','Ramos','Ribeiro','Rocha','Teixeira','Carvalho','Castro','Monteiro','Moreira','Freitas','Azevedo','Cunha','Duarte','Farias','Melo','Moura','Rezende','Vieira'),
          (((v_t - 1) * 50 + (v_slot - 1)) + 1) MOD 40,
          (((v_t - 1) * 50 + (v_slot - 1)) * 3 + 11) MOD 40
        )), 'Silva','Santos','Oliveira','Souza','Lima','Pereira','Ferreira','Almeida','Costa','Rodrigues','Martins','Araújo','Barbosa','Cardoso','Correia','Dias','Fernandes','Gomes','Lopes','Mendes','Nascimento','Nunes','Pinto','Ramos','Ribeiro','Rocha','Teixeira','Carvalho','Castro','Monteiro','Moreira','Freitas','Azevedo','Cunha','Duarte','Farias','Melo','Moura','Rezende','Vieira')
      );
      SET v_nome_mae = CONCAT(
        ELT(1 + ((((v_t - 1) * 50 + (v_slot - 1)) + 5) MOD 36), 'Ana','Beatriz','Camila','Daniela','Eduarda','Fernanda','Gabriela','Helena','Isabela','Juliana','Larissa','Mariana','Natália','Patrícia','Rafaela','Sabrina','Tatiane','Vanessa','Yasmin','Alice','Bianca','Carolina','Débora','Elisa','Flávia','Giovana','Heloísa','Ingrid','Jéssica','Letícia','Melissa','Nicole','Renata','Sofia','Taís','Vitória'), ' ',
        ELT(1 + (((((v_t - 1) * 50 + (v_slot - 1)) DIV 5) + 3) MOD 20), 'Clara','Cristina','Luiza','Aparecida','Regina','Vitória','Beatriz','Helena','Fernanda','Cecília','Lorena','Maitê','Pietra','Valentina','Alice','Lívia','Maya','Lara','Isis','Aurora'), ' ',
        ELT(1 + (((v_t - 1) * 50 + (v_slot - 1)) MOD 40), 'Silva','Santos','Oliveira','Souza','Lima','Pereira','Ferreira','Almeida','Costa','Rodrigues','Martins','Araújo','Barbosa','Cardoso','Correia','Dias','Fernandes','Gomes','Lopes','Mendes','Nascimento','Nunes','Pinto','Ramos','Ribeiro','Rocha','Teixeira','Carvalho','Castro','Monteiro','Moreira','Freitas','Azevedo','Cunha','Duarte','Farias','Melo','Moura','Rezende','Vieira'), ' ',
        ELT(1 + ((((v_t - 1) * 50 + (v_slot - 1)) + 13) MOD 40), 'Silva','Santos','Oliveira','Souza','Lima','Pereira','Ferreira','Almeida','Costa','Rodrigues','Martins','Araújo','Barbosa','Cardoso','Correia','Dias','Fernandes','Gomes','Lopes','Mendes','Nascimento','Nunes','Pinto','Ramos','Ribeiro','Rocha','Teixeira','Carvalho','Castro','Monteiro','Moreira','Freitas','Azevedo','Cunha','Duarte','Farias','Melo','Moura','Rezende','Vieira')
      );
      SET v_nome_pai = IF(v_slot = 42, NULL, CONCAT(
        ELT(1 + ((((v_t - 1) * 50 + (v_slot - 1)) + 9) MOD 36), 'André','Bruno','Carlos','Diego','Eduardo','Felipe','Gabriel','Henrique','Igor','João','Kaique','Lucas','Marcos','Nicolas','Otávio','Pedro','Rafael','Samuel','Thiago','Vinícius','Antônio','Bernardo','Caio','Daniel','Enzo','Fernando','Gustavo','Heitor','Isaac','José','Leandro','Matheus','Nathan','Paulo','Ricardo','Rodrigo'), ' ',
        ELT(1 + (((((v_t - 1) * 50 + (v_slot - 1)) DIV 5) + 2) MOD 20), 'Miguel','Henrique','Eduardo','Antônio','Gabriel','Luiz','Fernando','Augusto','Roberto','Paulo','Vinícius','Heitor','Davi','Benício','Theo','Arthur','Samuel','Isaac','Caio','Enzo'), ' ',
        ELT(1 + ((((v_t - 1) * 50 + (v_slot - 1)) + 17) MOD 40), 'Silva','Santos','Oliveira','Souza','Lima','Pereira','Ferreira','Almeida','Costa','Rodrigues','Martins','Araújo','Barbosa','Cardoso','Correia','Dias','Fernandes','Gomes','Lopes','Mendes','Nascimento','Nunes','Pinto','Ramos','Ribeiro','Rocha','Teixeira','Carvalho','Castro','Monteiro','Moreira','Freitas','Azevedo','Cunha','Duarte','Farias','Melo','Moura','Rezende','Vieira'), ' ',
        ELT(1 + (IF(
          ELT(1 + (((v_t - 1) * 50 + (v_slot - 1)) MOD 40), 'Silva','Santos','Oliveira','Souza','Lima','Pereira','Ferreira','Almeida','Costa','Rodrigues','Martins','Araújo','Barbosa','Cardoso','Correia','Dias','Fernandes','Gomes','Lopes','Mendes','Nascimento','Nunes','Pinto','Ramos','Ribeiro','Rocha','Teixeira','Carvalho','Castro','Monteiro','Moreira','Freitas','Azevedo','Cunha','Duarte','Farias','Melo','Moura','Rezende','Vieira') = ELT(1 + ((((v_t - 1) * 50 + (v_slot - 1)) * 3 + 11) MOD 40), 'Silva','Santos','Oliveira','Souza','Lima','Pereira','Ferreira','Almeida','Costa','Rodrigues','Martins','Araújo','Barbosa','Cardoso','Correia','Dias','Fernandes','Gomes','Lopes','Mendes','Nascimento','Nunes','Pinto','Ramos','Ribeiro','Rocha','Teixeira','Carvalho','Castro','Monteiro','Moreira','Freitas','Azevedo','Cunha','Duarte','Farias','Melo','Moura','Rezende','Vieira'),
          (((v_t - 1) * 50 + (v_slot - 1)) + 1) MOD 40,
          (((v_t - 1) * 50 + (v_slot - 1)) * 3 + 11) MOD 40
        )), 'Silva','Santos','Oliveira','Souza','Lima','Pereira','Ferreira','Almeida','Costa','Rodrigues','Martins','Araújo','Barbosa','Cardoso','Correia','Dias','Fernandes','Gomes','Lopes','Mendes','Nascimento','Nunes','Pinto','Ramos','Ribeiro','Rocha','Teixeira','Carvalho','Castro','Monteiro','Moreira','Freitas','Azevedo','Cunha','Duarte','Farias','Melo','Moura','Rezende','Vieira')
      ));
      SET v_entrada = IF(v_slot = 46, '2025-06-02', '2025-02-03');
      SET v_saida = IF(v_slot = 47, '2025-06-20', IF(v_slot = 48, '2025-09-15', NULL));

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
        DATE(CONCAT(2010 - CAST(v_serie_cod AS UNSIGNED), '-', LPAD(1 + ((v_slot - 1) MOD 12), 2, '0'), '-', LPAD(LEAST(28, v_slot), 2, '0'))),
        IF(v_slot IN (47, 48), 0, 1), 0, IF(v_slot IN (47, 48), 'INACTIVE', 'ACTIVE'), '', 0,
        IF(v_slot MOD 2 = 0, 'F', 'M'),
        LPAD(CAST(41000000000 + v_t * 100 + v_slot AS CHAR), 11, '0'),
        CONCAT(LPAD(10 + (v_slot MOD 80), 2, '0'), '.', LPAD((v_slot * 3) MOD 1000, 3, '0'), '.', LPAD((v_slot * 7) MOD 1000, 3, '0'), '-', v_slot MOD 10),
        CONCAT('113200', LPAD(v_t, 2, '0'), LPAD(v_slot, 2, '0')),
        CONCAT('1198', LPAD(v_t, 2, '0'), LPAD(v_slot, 6, '0')),
        CONCAT('1198', LPAD(v_t, 2, '0'), LPAD(v_slot, 6, '0')),
        ELT(1 + (v_slot MOD 6), 'Rua das Palmeiras', 'Avenida Paulista', 'Rua Augusta', 'Rua da Consolação', 'Rua Vergueiro', 'Rua Pamplona'),
        CAST(10 + v_slot AS CHAR),
        ELT(1 + (v_slot MOD 6), 'Centro', 'Bela Vista', 'Consolação', 'Jardins', 'Moema', 'Pinheiros'),
        'São Paulo', 'SP', CONCAT('013', LPAD(v_slot, 2, '0'), LPAD(10 + v_t, 3, '0')),
        v_nome_mae, v_nome_pai, CONCAT('3519', LPAD(v_t * 100 + v_slot, 8, '0')),
        'Brasileira', 'São Paulo', 'SP', ELT(1 + (v_slot MOD 4), 'Parda', 'Branca', 'Preta', 'Branca'),
        'SSP', 'SP', CONCAT('123456 01 55 2025 ', v_t, ' ', LPAD(v_slot, 5, '0')),
        LPAD(v_slot, 3, '0'), LPAD(v_t, 3, '0'), LPAD(v_t * 50 + v_slot, 5, '0'),
        LPAD(CAST(16000000000 + v_t * 100 + v_slot AS CHAR), 11, '0'),
        IF(v_slot MOD 31 = 0, 'rural', 'urbana'), 'Brasil'
      );
      SET v_aluno_id = LAST_INSERT_ID();

      INSERT INTO responsaveis (nome, email, senha_hash, cpf, telefone, celular, rg, data_nascimento, endereco, numero, bairro, cidade, uf, cep, ativo, password)
      VALUES (
        v_nome_mae, CONCAT('mae.', v_nick, '@educateste.local'), v_hash,
        LPAD(CAST(42000000000 + v_t * 100 + v_slot AS CHAR), 11, '0'),
        CONCAT('1197', LPAD(v_t, 2, '0'), LPAD(v_slot, 6, '0')),
        CONCAT('1197', LPAD(v_t, 2, '0'), LPAD(v_slot, 6, '0')),
        CONCAT('20.', LPAD(v_slot, 3, '0'), '.', LPAD(v_t, 3, '0'), '-1'),
        DATE(CONCAT('1983-', LPAD(1 + (v_slot MOD 12), 2, '0'), '-', LPAD(LEAST(28, v_slot), 2, '0'))),
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
          LPAD(CAST(43000000000 + v_t * 100 + v_slot AS CHAR), 11, '0'),
          CONCAT('1196', LPAD(v_t, 2, '0'), LPAD(v_slot, 6, '0')),
          CONCAT('1196', LPAD(v_t, 2, '0'), LPAD(v_slot, 6, '0')),
          CONCAT('30.', LPAD(v_slot, 3, '0'), '.', LPAD(v_t, 3, '0'), '-2'),
          DATE(CONCAT('1981-', LPAD(1 + (v_slot MOD 12), 2, '0'), '-', LPAD(LEAST(28, v_slot), 2, '0'))),
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
        IF(v_slot MOD 3 = 0, NULL, CONCAT('ET25', LPAD(v_t * 50 + v_slot, 8, '0'))),
        IF(v_slot MOD 4 = 0, 'Hospital das Clínicas', 'Santa Casa de São Paulo'),
        IF(v_slot MOD 11 = 0, 'Dipirona', NULL),
        IF(v_slot MOD 19 = 0, 'Bombinha de asma (salbutamol) se crise', NULL),
        IF(v_slot = 42, 'Pai falecido. Responsável legal: mãe.', IF(v_slot MOD 19 = 0, 'Asma leve', NULL)),
        IF(v_slot = 50, 'Laudo de TDAH. Acompanhamento pedagógico e tempo extra em prova.', IF(v_slot MOD 41 = 0, 'Usa óculos para miopia', NULL)),
        IF(v_slot = 42, CONCAT('Avó ', SUBSTRING_INDEX(v_nome, ' ', -1)), v_nome_mae),
        CONCAT('1197', LPAD(v_t, 2, '0'), LPAD(v_slot, 6, '0')),
        IF(v_slot = 42, 'Avó materna', 'Mãe'),
        IF(v_slot MOD 13 = 0, 'Intolerância à lactose', NULL),
        IF(v_slot MOD 13 = 0, 'Merenda sem leite.', NULL),
        IF(v_slot IN (17, 50), 1, 0),
        IF(v_slot IN (17, 50), 'escolar', 'proprio'),
        IF(v_slot IN (17, 50), 'Linha EM manhã', NULL),
        IF(v_slot IN (17, 50), 'Rua das Palmeiras', NULL),
        IF(v_slot IN (17, 50), 'Van Educa — Sr. Paulo', NULL),
        IF(v_slot IN (17, 50), '11960001000', NULL)
      );

      INSERT INTO alunos_documentos (aluno_id, tipo, titulo, status, observacao, entregue_em, created_by) VALUES
        (v_aluno_id, 'rg', 'RG', 'entregue', 'ET25', NOW(), v_admin),
        (v_aluno_id, 'cpf', 'CPF', 'entregue', 'ET25', NOW(), v_admin),
        (v_aluno_id, 'certidao_nascimento', 'Certidão de nascimento', IF(v_slot = 41, 'pendente', 'entregue'), 'ET25', IF(v_slot = 41, NULL, NOW()), v_admin),
        (v_aluno_id, 'comprovante_residencia', 'Comprovante de residência', 'entregue', 'ET25', NOW(), v_admin),
        (v_aluno_id, 'foto', 'Foto 3x4', 'entregue', 'ET25', NOW(), v_admin),
        (v_aluno_id, 'historico', 'Histórico escolar', IF(v_slot = 41, 'pendente', 'entregue'), 'ET25', IF(v_slot = 41, NULL, NOW()), v_admin);

      IF v_slot IN (45, 46, 47) THEN
        INSERT INTO alunos_documentos (aluno_id, tipo, titulo, status, observacao, entregue_em, created_by)
        VALUES (v_aluno_id, 'declaracao_transferencia', 'Declaração de transferência', 'entregue', 'ET25', NOW(), v_admin);
      END IF;

      INSERT INTO matricula (aluno_id, turma_id, ano_letivo_id, data_entrada, data_saida, status)
      VALUES (v_aluno_id, v_turma_id, v_ano_id, v_entrada, v_saida, IF(v_slot = 47, 'transferido', 'ativa'));

      INSERT INTO alunos_turma_chamada (aluno_id, turma_id, ano_letivo_id, numero_chamada, entrada_tardia, marcado_tr, data_entrada_turma)
      VALUES (v_aluno_id, v_turma_id, v_ano_id, v_slot, IF(v_slot = 46, 1, 0), IF(v_slot = 47, 1, 0), v_entrada);

      IF v_slot IN (45, 46) THEN
        INSERT INTO matricula_transferencias (protocolo, direcao, aluno_id, escola_nome, escola_cidade, escola_uf, escola_inep, motivo, observacao, data_transferencia, docs_entregues_em, criado_por)
        VALUES (
          CONCAT('ET25-E-', v_nick), 'entrada', v_aluno_id,
          IF(v_slot = 46, 'Colégio Santa Luzia', 'Escola Estadual Dom Pedro II'),
          IF(v_slot = 46, 'Campinas', 'Ribeirão Preto'), 'SP',
          IF(v_slot = 46, '35123456', '35990001'),
          IF(v_slot = 46, 'Mudança de cidade no meio do ano letivo', 'Ingresso vindo de outra escola no início do ano'),
          'ET25', v_entrada, NOW(), v_admin
        );
      END IF;
      IF v_slot = 47 THEN
        INSERT INTO matricula_transferencias (protocolo, direcao, aluno_id, turma_origem_id, escola_nome, escola_cidade, escola_uf, escola_inep, motivo, observacao, data_transferencia, docs_entregues_em, criado_por)
        VALUES (CONCAT('ET25-S-', v_nick), 'saida', v_aluno_id, v_turma_id, 'Colégio Horizonte', 'Santos', 'SP', '35118820', 'Transferência a pedido da família', 'ET25', '2025-06-20', NOW(), v_admin);
        INSERT INTO alunos_historico_status (student_id, old_status, new_status, reason, observation, changed_by, ip)
        VALUES (v_aluno_id, 'ACTIVE', 'INACTIVE', 'transferencia', 'ET25 saída em 20/06/2025', v_admin, '127.0.0.1');
      END IF;
      IF v_slot = 48 THEN
        INSERT INTO alunos_historico_status (student_id, old_status, new_status, reason, observation, changed_by, ip)
        VALUES (v_aluno_id, 'ACTIVE', 'INACTIVE', 'evasao', 'ET25 deixou de comparecer em 15/09/2025', v_admin, '127.0.0.1');
      END IF;

      SET v_slot = v_slot + 1;
    END WHILE;
    SET v_t = v_t + 1;
  END WHILE;

  INSERT INTO diario_aulas (
    grade_horaria_id, professor_id, turma_id, materia_id, data_aula, horario_de, horario_ate,
    execucao, conteudo_realizado, observacoes, tipo_aula, status, finalizada_at
  )
  SELECT gh.id, gh.professor_id, gh.turma_id, gh.materia_id, d.data_aula, gh.horario_de, gh.horario_ate,
         CASE
           WHEN (CRC32(CONCAT(gh.id, '|', d.data_aula)) % 100) < 8 THEN 'parcial'
           WHEN (CRC32(CONCAT(gh.id, '|', d.data_aula)) % 100) < 12 THEN 'alterado'
           ELSE 'conforme_planejado'
         END,
         CONCAT('ET25 ', m.nome, ' — aula ministrada em ', t.nome, '.'),
         'ET25-SEED',
         'regular', 'finalizada', NOW()
  FROM grade_horaria gh
  INNER JOIN turmas t ON t.id = gh.turma_id AND t.observacoes LIKE 'ET25 %'
  INNER JOIN materias m ON m.id = gh.materia_id
  INNER JOIN (
    SELECT DATE('2025-02-03') + INTERVAL seq DAY AS data_aula
    FROM (
      SELECT a.n + b.n * 10 + c.n * 100 AS seq
      FROM (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
      CROSS JOIN (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
      CROSS JOIN (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3) c
    ) nums
    WHERE DATE('2025-02-03') + INTERVAL seq DAY BETWEEN '2025-02-03' AND '2025-12-12'
      AND WEEKDAY(DATE('2025-02-03') + INTERVAL seq DAY) < 5
  ) d ON gh.dia_semana = WEEKDAY(d.data_aula) + 1
  WHERE NOT EXISTS (
    SELECT 1 FROM calendario_letivo_eventos e
    WHERE e.calendario_id = v_cal
      AND e.tipo IN ('feriado','recesso','suspensao')
      AND d.data_aula BETWEEN e.data_inicio AND e.data_fim
  );

  INSERT IGNORE INTO diario_frequencias (diario_aula_id, aluno_id, situacao, origem)
  SELECT da.id, mt.aluno_id,
    CASE
      WHEN RIGHT(a.nickname, 2) = '44' AND (CRC32(CONCAT(a.id, '|', da.data_aula)) % 100) < 32 THEN 'falta'
      WHEN RIGHT(a.nickname, 2) = '44' THEN 'presente'
      WHEN (CRC32(CONCAT(a.id, '|', da.data_aula)) % 100) < 6 THEN 'falta'
      WHEN (CRC32(CONCAT(a.id, '|', da.data_aula)) % 100) < 9 THEN 'atraso'
      WHEN (CRC32(CONCAT(a.id, '|', da.data_aula)) % 100) < 11 THEN 'falta_justificada'
      WHEN (CRC32(CONCAT(a.id, '|', da.data_aula)) % 100) < 13 THEN 'saida_antecipada'
      ELSE 'presente'
    END,
    'manual_diario'
  FROM diario_aulas da
  INNER JOIN matricula mt ON mt.turma_id = da.turma_id AND mt.ano_letivo_id = v_ano_id
  INNER JOIN alunos a ON a.id = mt.aluno_id AND a.nickname LIKE 'et25.%'
  WHERE da.observacoes LIKE 'ET25-SEED%'
    AND da.data_aula >= mt.data_entrada
    AND (mt.data_saida IS NULL OR da.data_aula <= mt.data_saida);

  SET v_cat_falta = (SELECT id FROM ocorrencias_categorias WHERE slug = 'administrativa' LIMIT 1);
  SET v_cat_atraso = (SELECT id FROM ocorrencias_categorias WHERE slug = 'atraso' LIMIT 1);
  SET v_cat_saida = (SELECT id FROM ocorrencias_categorias WHERE slug = 'saida_antecipada' LIMIT 1);
  SET v_cat_elogio = (SELECT id FROM ocorrencias_categorias WHERE slug = 'elogio' LIMIT 1);

  INSERT INTO alunos_ocorrencias (
    aluno_id, data_ocorrencia, titulo, detalhe, nivel_gravidade, atitude_coordenacao,
    enviar_pais, criado_por, categoria_id, status, turma_id, ano_letivo_id, diario_aula_id, materia_id, local
  )
  SELECT df.aluno_id, TIMESTAMP(da.data_aula, da.horario_de),
    CASE df.situacao
      WHEN 'falta' THEN 'ET25 Falta'
      WHEN 'falta_justificada' THEN 'ET25 Falta justificada'
      WHEN 'atraso' THEN 'ET25 Atraso'
      ELSE 'ET25 Saída antecipada'
    END,
    CONCAT('ET25 Registro automático a partir do diário de ', m.nome, ' em ', DATE_FORMAT(da.data_aula, '%d/%m/%Y'), '.'),
    CASE df.situacao WHEN 'falta' THEN 'moderado' WHEN 'atraso' THEN 'leve' ELSE 'leve' END,
    IF(df.situacao = 'falta', 'orientacao', NULL),
    IF(df.situacao IN ('falta','atraso'), 1, 0),
    v_admin,
    CASE df.situacao
      WHEN 'atraso' THEN v_cat_atraso
      WHEN 'saida_antecipada' THEN v_cat_saida
      ELSE v_cat_falta
    END,
    'encerrada', da.turma_id, v_ano_id, da.id, da.materia_id, 'Sala de aula'
  FROM diario_frequencias df
  INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
  INNER JOIN materias m ON m.id = da.materia_id
  WHERE da.observacoes LIKE 'ET25-SEED%'
    AND df.situacao IN ('falta','falta_justificada','atraso','saida_antecipada');

  INSERT INTO alunos_ocorrencias (aluno_id, data_ocorrencia, titulo, detalhe, nivel_gravidade, enviar_pais, criado_por, categoria_id, status, turma_id, ano_letivo_id, local)
  SELECT a.id, '2025-04-10 10:00:00', 'ET25 Elogio', 'ET25 Participação destacada no conselho de turma.', 'leve', 1, v_admin, v_cat_elogio, 'encerrada', a.turma_id, v_ano_id, 'Sala de aula'
  FROM alunos a
  WHERE a.nickname LIKE 'et25.%' AND RIGHT(a.nickname, 2) = '05';

  INSERT INTO faltas_eventos (nome, bimestre, ano_letivo, turmas_json, origem, created_by, ativo)
  SELECT CONCAT('ET25 Faltas ', n, 'º trimestre 2025'), CAST(n AS CHAR), 2025,
         (SELECT JSON_ARRAYAGG(id) FROM turmas WHERE observacoes LIKE 'ET25 %'), 'diario', v_admin, 1
  FROM (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3) p;

  INSERT INTO faltas_lancamentos (evento_id, aluno_id, materia_id, faltas, created_by)
  SELECT e.id, x.aluno_id, x.materia_id, x.faltas, v_admin
  FROM faltas_eventos e
  INNER JOIN (
    SELECT df.aluno_id, da.materia_id,
           SUM(df.situacao IN ('falta','falta_justificada')) faltas,
           CASE
             WHEN da.data_aula <= '2025-05-16' THEN 1
             WHEN da.data_aula <= '2025-08-29' THEN 2
             ELSE 3
           END bim
    FROM diario_frequencias df
    INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
    WHERE da.observacoes LIKE 'ET25-SEED%'
    GROUP BY df.aluno_id, da.materia_id, bim
    HAVING faltas > 0
  ) x ON e.nome = CONCAT('ET25 Faltas ', x.bim, 'º trimestre 2025');

  SET v_t = 1;
  WHILE v_t <= 12 DO
    SET v_serie_cod = ELT(v_t, '1','1','1','1','2','2','2','2','3','3','3','3');
    SET v_letra = ELT(v_t, 'A','B','C','D','A','B','C','D','A','B','C','D');
    SET v_turma_nome = CONCAT(v_serie_cod, 'º ', v_letra);
    SET v_turma_id = (SELECT id FROM turmas WHERE ano_letivo = 2025 AND nome = v_turma_nome AND observacoes LIKE 'ET25 %' LIMIT 1);
    SET v_bim = 1;
    WHILE v_bim <= 3 DO
      SET v_ev = 1;
      WHILE v_ev <= 5 DO
        SET v_titulo = CONCAT('ET25 ', ELT(v_ev, 'Simulado 1','Simulado 2','Simulado 3','Prova Bimestral','Rec'), ' — ', v_turma_nome, ' T', v_bim);
        SET v_data_ev = ELT(v_bim,
          ELT(v_ev, '2025-02-20','2025-03-13','2025-04-10','2025-05-08','2025-05-15'),
          ELT(v_ev, '2025-06-05','2025-06-12','2025-07-10','2025-08-14','2025-08-21'),
          ELT(v_ev, '2025-09-11','2025-10-09','2025-10-30','2025-11-19','2025-11-27')
        );
        INSERT INTO provas_blocos (
          titulo, descricao, data_prova, hora_inicio, hora_fim, criado_por, tipo_prova,
          formato_evento, configuracao_nota, ano_letivo, bimestre, tipo_avaliacao_id,
          visivel_no_portal_aluno, ativo, liberado, status, turma_id
        ) VALUES (
          v_titulo, 'ET25', v_data_ev, '07:10:00', '09:40:00', v_admin, 'original',
          'lancamento_nota', 'coordenacao_calcula', 2025, v_bim,
          ELT(v_ev, v_tipo_s1, v_tipo_s2, v_tipo_s3, v_tipo_pb, v_tipo_rec),
          1, 1, 1, 'liberado', v_turma_id
        );
        SET v_bloco_id = LAST_INSERT_ID();
        INSERT IGNORE INTO provas_blocos_turmas (bloco_id, turma_id) VALUES (v_bloco_id, v_turma_id);
        INSERT INTO provas_blocos_professores (bloco_id, professor_id, materia_id, quantidade_questoes)
        SELECT v_bloco_id, p.id, m.id, 1
        FROM (
          SELECT 'Língua Portuguesa' n, 'ET25-LPO' c UNION ALL SELECT 'Matemática','ET25-MAT'
          UNION ALL SELECT 'História','ET25-HIS' UNION ALL SELECT 'Geografia','ET25-GEO'
          UNION ALL SELECT 'Física','ET25-FIS' UNION ALL SELECT 'Química','ET25-QUI'
          UNION ALL SELECT 'Biologia','ET25-BIO' UNION ALL SELECT 'Sociologia','ET25-SOC'
        ) map
        INNER JOIN materias m ON m.nome = map.n
        INNER JOIN professores p ON p.codigo_prof = map.c;
        INSERT IGNORE INTO provas_blocos_professores_turmas (bloco_professor_id, turma_id)
        SELECT bp.id, v_turma_id FROM provas_blocos_professores bp WHERE bp.bloco_id = v_bloco_id;
        SET v_ev = v_ev + 1;
      END WHILE;

      SET v_titulo = CONCAT('et25-', v_serie_cod, LOWER(v_letra), '-t', v_bim);
      INSERT INTO boletim_regras (
        nome, codigo, descricao_curta, formula_final, series_ids, turmas_ids, exibir_em,
        ano_letivo, bimestre, nota_minima_aprovacao, usar_resultado_aprovacao,
        vis_aluno, vis_pais, vis_coordenacao, round_mode, decimal_places,
        default_data_inicio, default_data_fim, ativo
      ) VALUES (
        CONCAT('Boletim ', v_turma_nome, ' T', v_bim, ' 2025'),
        v_titulo,
        'ET25 Média de Simulado 1, 2, 3 e Prova. Rec substitui a prova só até 6,00.',
        '(SIM1 + SIM2 + SIM3 + max(PROVA, min(REC, 6))) / 4',
        JSON_ARRAY(ELT(v_t, v_s1,v_s1,v_s1,v_s1,v_s2,v_s2,v_s2,v_s2,v_s3,v_s3,v_s3,v_s3)),
        JSON_ARRAY(v_turma_id),
        'boletim', 2025, v_bim, 6.00, 1, 1, 1, 1, 'none', 2,
        ELT(v_bim, '2025-02-03','2025-05-19','2025-09-01'),
        ELT(v_bim, '2025-05-16','2025-08-29','2025-12-12'),
        1
      );
      SET v_regra = LAST_INSERT_ID();
      INSERT INTO boletim_componentes (regra_id, codigo, nome, source_type, calc_type, peso, blocos_ids, config_json, obrigatorio, ordem, ativo)
      SELECT v_regra, x.cod, x.nom, x.src, 'media', 1,
             CAST((SELECT pb.id FROM provas_blocos pb WHERE pb.titulo = CONCAT('ET25 ', x.nom, ' — ', v_turma_nome, ' T', v_bim) AND pb.deleted_at IS NULL LIMIT 1) AS CHAR),
             x.cfg, x.obr, x.ord, 1
      FROM (
        SELECT 'SIM1' cod, 'Simulado 1' nom, 'provas_sistema' src, NULL cfg, 1 obr, 1 ord
        UNION ALL SELECT 'SIM2', 'Simulado 2', 'provas_sistema', NULL, 1, 2
        UNION ALL SELECT 'SIM3', 'Simulado 3', 'provas_sistema', NULL, 1, 3
        UNION ALL SELECT 'PROVA', 'Prova Bimestral', 'provas_sistema', NULL, 1, 4
        UNION ALL SELECT 'REC', 'Rec', 'provas_sistema', NULL, 0, 5
        UNION ALL SELECT 'PROVA_POS', 'Prova após recuperação', 'calculado', '{"expressao":"max(PROVA, min(REC, 6))","formula_mode":"single"}', 0, 6
        UNION ALL SELECT 'MEDIA', 'Média do trimestre', 'calculado', '{"expressao":"(SIM1 + SIM2 + SIM3 + PROVA_POS) / 4","formula_mode":"single"}', 0, 7
      ) x;
      SET v_bim = v_bim + 1;
    END WHILE;
    SET v_t = v_t + 1;
  END WHILE;

  INSERT IGNORE INTO provas_blocos_notas_lancadas (bloco_id, professor_id, materia_id, turma_id, aluno_id, nota)
  SELECT pb.id, bp.professor_id, bp.materia_id, pbt.turma_id, a.id,
    ROUND(CASE
      WHEN RIGHT(a.nickname, 2) = '49' THEN 2 + (CRC32(CONCAT(a.id,'|',bp.materia_id,'|',pb.bimestre,'|',ta.nome)) % 36) / 10
      WHEN RIGHT(a.nickname, 2) = '43' THEN 5 + (CRC32(CONCAT(a.id,'|',bp.materia_id,'|',pb.bimestre,'|',ta.nome)) % 10) / 10
      ELSE 3.5 + (CRC32(CONCAT(a.id,'|',bp.materia_id,'|',pb.bimestre,'|',ta.nome)) % 66) / 10
    END, 2)
  FROM provas_blocos pb
  INNER JOIN provas_tipos_avaliacao ta ON ta.id = pb.tipo_avaliacao_id AND ta.nome <> 'Rec'
  INNER JOIN provas_blocos_professores bp ON bp.bloco_id = pb.id
  INNER JOIN provas_blocos_turmas pbt ON pbt.bloco_id = pb.id
  INNER JOIN matricula mt ON mt.turma_id = pbt.turma_id AND mt.ano_letivo_id = v_ano_id
  INNER JOIN alunos a ON a.id = mt.aluno_id AND a.nickname LIKE 'et25.%'
  WHERE pb.titulo LIKE 'ET25 %' AND pb.deleted_at IS NULL
    AND pb.data_prova >= mt.data_entrada
    AND (mt.data_saida IS NULL OR pb.data_prova <= mt.data_saida);

  INSERT IGNORE INTO provas_blocos_notas_lancadas (bloco_id, professor_id, materia_id, turma_id, aluno_id, nota)
  SELECT rec.id, bp.professor_id, bp.materia_id, base.turma_id, base.aluno_id,
         ROUND(3 + (CRC32(CONCAT(base.aluno_id,'|rec|',bp.materia_id,'|',base.bimestre)) % 70) / 10, 2)
  FROM (
    SELECT n.aluno_id, n.turma_id, n.materia_id, pb.bimestre, AVG(n.nota) media, COUNT(*) qtd
    FROM provas_blocos_notas_lancadas n
    INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.titulo LIKE 'ET25 %' AND pb.deleted_at IS NULL
    INNER JOIN provas_tipos_avaliacao ta ON ta.id = pb.tipo_avaliacao_id AND ta.nome <> 'Rec'
    GROUP BY n.aluno_id, n.turma_id, n.materia_id, pb.bimestre
    HAVING qtd = 4 AND media < 6
  ) base
  INNER JOIN alunos a ON a.id = base.aluno_id AND RIGHT(a.nickname, 2) <> '43'
  INNER JOIN provas_blocos rec ON rec.titulo = CONCAT('ET25 Rec — ',
      (SELECT t.nome FROM turmas t WHERE t.id = base.turma_id LIMIT 1), ' T', base.bimestre)
    AND rec.deleted_at IS NULL
  INNER JOIN provas_blocos_professores bp ON bp.bloco_id = rec.id AND bp.materia_id = base.materia_id
  INNER JOIN matricula mt ON mt.aluno_id = base.aluno_id AND mt.turma_id = base.turma_id AND mt.ano_letivo_id = v_ano_id
  WHERE rec.data_prova >= mt.data_entrada
    AND (mt.data_saida IS NULL OR rec.data_prova <= mt.data_saida);

  INSERT INTO notas_tipo_finais (tipo_avaliacao_id, aluno_id, materia_id, turma_id, ano_letivo, periodo, nota_final, eventos_qtd)
  SELECT pb.tipo_avaliacao_id, n.aluno_id, n.materia_id, n.turma_id, 2025, pb.bimestre, n.nota, 1
  FROM provas_blocos_notas_lancadas n
  INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.titulo LIKE 'ET25 %'
  ON DUPLICATE KEY UPDATE nota_final = VALUES(nota_final), eventos_qtd = 1;

  DROP TABLE IF EXISTS et25_tmp_media;
  CREATE TABLE et25_tmp_media (
    aluno_id INT NOT NULL,
    turma_id INT NOT NULL,
    materia_id INT NOT NULL,
    periodo TINYINT NOT NULL,
    sim1 DECIMAL(6,2) NULL,
    sim2 DECIMAL(6,2) NULL,
    sim3 DECIMAL(6,2) NULL,
    prova DECIMAL(6,2) NULL,
    rec DECIMAL(6,2) NULL,
    media_antes DECIMAL(8,2) NULL,
    media_final DECIMAL(8,2) NULL,
    PRIMARY KEY (aluno_id, turma_id, materia_id, periodo),
    KEY idx_et25_media_turma (turma_id, periodo)
  ) ENGINE=InnoDB;

  INSERT INTO et25_tmp_media (aluno_id, turma_id, materia_id, periodo, sim1, sim2, sim3, prova, rec)
  SELECT n.aluno_id, n.turma_id, n.materia_id, pb.bimestre,
    MAX(CASE WHEN ta.nome = 'Simulado 1' THEN n.nota END),
    MAX(CASE WHEN ta.nome = 'Simulado 2' THEN n.nota END),
    MAX(CASE WHEN ta.nome = 'Simulado 3' THEN n.nota END),
    MAX(CASE WHEN ta.nome = 'Prova Bimestral' THEN n.nota END),
    MAX(CASE WHEN ta.nome = 'Rec' THEN n.nota END)
  FROM provas_blocos_notas_lancadas n
  INNER JOIN provas_blocos pb ON pb.id = n.bloco_id AND pb.titulo LIKE 'ET25 %'
  INNER JOIN provas_tipos_avaliacao ta ON ta.id = pb.tipo_avaliacao_id
  GROUP BY n.aluno_id, n.turma_id, n.materia_id, pb.bimestre;

  UPDATE et25_tmp_media
  SET media_antes = ROUND((IFNULL(sim1,0)+IFNULL(sim2,0)+IFNULL(sim3,0)+IFNULL(prova,0))
        / NULLIF((sim1 IS NOT NULL)+(sim2 IS NOT NULL)+(sim3 IS NOT NULL)+(prova IS NOT NULL), 0), 2),
      media_final = ROUND((IFNULL(sim1,0)+IFNULL(sim2,0)+IFNULL(sim3,0)
        + IF(rec IS NULL, IFNULL(prova,0), GREATEST(IFNULL(prova,0), LEAST(rec, 6))))
        / NULLIF((sim1 IS NOT NULL)+(sim2 IS NOT NULL)+(sim3 IS NOT NULL)+(prova IS NOT NULL), 0), 2);

  INSERT INTO boletim_geracoes (regra_id, periodo_ref, versao, vigente, modo, usuario_id, usuario_nome, alunos_processados, linhas_geradas)
  SELECT r.id, CONCAT('2025-T', r.bimestre), 1, 1, 'gerar', v_admin, 'Seed ET25', 50, 400
  FROM boletim_regras r
  WHERE r.codigo LIKE 'et25-%';

  INSERT INTO boletim_resultados_gerados (
    regra_id, aluno_id, periodo_ref, data_inicio, data_fim, materia_id, materia_nome, materia_ref,
    ordem_linha, colunas_json, notas_json, media_final, preview, geracao_id, versao, vigente
  )
  SELECT r.id, x.aluno_id, CONCAT('2025-T', x.periodo),
         ELT(x.periodo, '2025-02-03','2025-05-19','2025-09-01'),
         ELT(x.periodo, '2025-05-16','2025-08-29','2025-12-12'),
         x.materia_id, m.nome, m.nome, mcc.ordem_boletim,
         '[{"codigo":"SIM1","nome":"Simulado 1"},{"codigo":"SIM2","nome":"Simulado 2"},{"codigo":"SIM3","nome":"Simulado 3"},{"codigo":"PROVA","nome":"Prova Bimestral"},{"codigo":"REC","nome":"Rec"},{"codigo":"media_final","nome":"Média"}]',
         JSON_OBJECT(
           'SIM1', x.sim1, 'SIM2', x.sim2, 'SIM3', x.sim3, 'PROVA', x.prova, 'REC', x.rec,
           'media_antes_rec', x.media_antes, 'media_final', x.media_final
         ),
         x.media_final, 0, g.id, 1, 1
  FROM et25_tmp_media x
  INNER JOIN materias m ON m.id = x.materia_id
  INNER JOIN turmas t ON t.id = x.turma_id
  INNER JOIN boletim_regras r ON r.codigo = CONCAT('et25-', LEFT(t.nome, 1), LOWER(SUBSTRING(t.nome, 4, 1)), '-t', x.periodo) AND r.bimestre = x.periodo
  INNER JOIN boletim_geracoes g ON g.regra_id = r.id AND g.periodo_ref = CONCAT('2025-T', x.periodo) AND g.vigente = 1
  LEFT JOIN matrizes_curriculares_componentes mcc ON mcc.matriz_id = t.matriz_curricular_id AND mcc.materia_id = x.materia_id;

  DROP TABLE IF EXISTS et25_tmp_freq;
  CREATE TABLE et25_tmp_freq (
    aluno_id INT NOT NULL,
    turma_id INT NOT NULL,
    periodo TINYINT NOT NULL,
    aulas INT NOT NULL,
    presencas INT NOT NULL,
    PRIMARY KEY (aluno_id, turma_id, periodo)
  ) ENGINE=InnoDB;

  INSERT INTO et25_tmp_freq (aluno_id, turma_id, periodo, aulas, presencas)
  SELECT aluno_id, turma_id, periodo, COUNT(*), SUM(presenca)
  FROM (
    SELECT df.aluno_id, da.turma_id,
      CASE
        WHEN da.data_aula <= '2025-05-16' THEN 1
        WHEN da.data_aula <= '2025-08-29' THEN 2
        ELSE 3
      END periodo,
      IF(df.situacao IN ('presente','atraso','saida_antecipada'), 1, 0) presenca
    FROM diario_frequencias df
    INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
    WHERE da.observacoes LIKE 'ET25-SEED%'
  ) z
  GROUP BY aluno_id, turma_id, periodo;

  INSERT INTO fechamento_periodo (
    turma_id, ano_letivo, periodo_tipo, periodo_numero, periodo_ref, status,
    regra_academica_id, homologado_em, homologado_por, justificativa, vigente, vigente_chave
  )
  SELECT t.id, 2025, per.tipo, per.num, CONCAT('ET25-', per.ref), 'HOMOLOGADO',
         v_regra_acad, NOW(), v_admin, 'ET25 fechamento homologado pelo seed', 1,
         CONCAT('ET25-', t.id, '-', per.ref)
  FROM turmas t
  CROSS JOIN (
    SELECT 'trimestre' tipo, 1 num, 'T1' ref
    UNION ALL SELECT 'trimestre', 2, 'T2'
    UNION ALL SELECT 'trimestre', 3, 'T3'
    UNION ALL SELECT 'ano', 0, 'ANO'
  ) per
  WHERE t.observacoes LIKE 'ET25 %';

  INSERT INTO fechamento_periodo_historico (fechamento_id, status_anterior, status_novo, justificativa, usuario_id)
  SELECT f.id, 'ABERTO', 'HOMOLOGADO', 'ET25', v_admin
  FROM fechamento_periodo f
  WHERE f.vigente_chave LIKE 'ET25-%';

  INSERT INTO resultado_academico (
    aluno_id, turma_id, ano_letivo, periodo_tipo, periodo_numero, versao, status,
    situacao, rotulo, media_final, frequencia_percentual, faltas, regra_id,
    fechamento_periodo_id, homologado_em, homologado_por
  )
  SELECT b.aluno_id, b.turma_id, 2025, 'trimestre', b.periodo, 1, 'homologado',
    CASE
      WHEN RIGHT(a.nickname, 2) = '47' AND b.periodo >= 2 THEN 'transferido'
      WHEN RIGHT(a.nickname, 2) = '48' AND b.periodo = 3 THEN 'desistente'
      WHEN IFNULL(f.presencas, 0) / NULLIF(f.aulas, 0) * 100 < 75 THEN 'reprovado_frequencia'
      WHEN RIGHT(a.nickname, 2) = '43' AND b.media < 6 AND b.media >= 5 THEN 'aprovado_conselho'
      WHEN b.media >= 6 AND b.antes < 6 THEN 'aprovado_recuperacao'
      WHEN b.media >= 6 THEN 'aprovado'
      ELSE 'reprovado_rendimento'
    END,
    CASE
      WHEN RIGHT(a.nickname, 2) = '47' AND b.periodo >= 2 THEN 'Transferido'
      WHEN RIGHT(a.nickname, 2) = '48' AND b.periodo = 3 THEN 'Desistente'
      WHEN IFNULL(f.presencas, 0) / NULLIF(f.aulas, 0) * 100 < 75 THEN 'Reprovado por frequência'
      WHEN RIGHT(a.nickname, 2) = '43' AND b.media < 6 AND b.media >= 5 THEN 'Aprovado pelo Conselho'
      WHEN b.media >= 6 AND b.antes < 6 THEN 'Aprovado após recuperação'
      WHEN b.media >= 6 THEN 'Aprovado'
      ELSE 'Reprovado por rendimento'
    END,
    b.media,
    ROUND(IFNULL(f.presencas, 0) / NULLIF(f.aulas, 0) * 100, 2),
    IFNULL(f.aulas, 0) - IFNULL(f.presencas, 0),
    v_regra_acad,
    fp.id, NOW(), v_admin
  FROM (
    SELECT aluno_id, turma_id, periodo, ROUND(AVG(media_final), 2) media, ROUND(AVG(media_antes), 2) antes
    FROM et25_tmp_media
    GROUP BY aluno_id, turma_id, periodo
  ) b
  INNER JOIN alunos a ON a.id = b.aluno_id
  LEFT JOIN et25_tmp_freq f ON f.aluno_id = b.aluno_id AND f.turma_id = b.turma_id AND f.periodo = b.periodo
  INNER JOIN fechamento_periodo fp ON fp.turma_id = b.turma_id AND fp.periodo_tipo = 'trimestre' AND fp.periodo_numero = b.periodo AND fp.vigente = 1 AND fp.vigente_chave LIKE 'ET25-%';

  INSERT INTO resultado_academico (
    aluno_id, turma_id, ano_letivo, periodo_tipo, periodo_numero, versao, status,
    situacao, rotulo, media_final, frequencia_percentual, faltas, regra_id,
    fechamento_periodo_id, homologado_em, homologado_por
  )
  SELECT b.aluno_id, b.turma_id, 2025, 'ano', 0, 1, 'homologado',
    CASE
      WHEN RIGHT(a.nickname, 2) = '47' THEN 'transferido'
      WHEN RIGHT(a.nickname, 2) = '48' THEN 'desistente'
      WHEN IFNULL(f.presencas, 0) / NULLIF(f.aulas, 0) * 100 < 75 THEN 'reprovado_frequencia'
      WHEN RIGHT(a.nickname, 2) = '43' AND b.media < 6 AND b.media >= 5 THEN 'aprovado_conselho'
      WHEN b.media >= 6 AND b.antes < 6 THEN 'aprovado_recuperacao'
      WHEN b.media >= 6 THEN 'aprovado'
      ELSE 'reprovado_rendimento'
    END,
    CASE
      WHEN RIGHT(a.nickname, 2) = '47' THEN 'Transferido'
      WHEN RIGHT(a.nickname, 2) = '48' THEN 'Desistente'
      WHEN IFNULL(f.presencas, 0) / NULLIF(f.aulas, 0) * 100 < 75 THEN 'Reprovado por frequência'
      WHEN RIGHT(a.nickname, 2) = '43' AND b.media < 6 AND b.media >= 5 THEN 'Aprovado pelo Conselho'
      WHEN b.media >= 6 AND b.antes < 6 THEN 'Aprovado após recuperação'
      WHEN b.media >= 6 THEN 'Aprovado'
      ELSE 'Reprovado por rendimento'
    END,
    b.media,
    ROUND(IFNULL(f.presencas, 0) / NULLIF(f.aulas, 0) * 100, 2),
    IFNULL(f.aulas, 0) - IFNULL(f.presencas, 0),
    v_regra_acad, fp.id, NOW(), v_admin
  FROM (
    SELECT aluno_id, turma_id, ROUND(AVG(media_final), 2) media, ROUND(AVG(media_antes), 2) antes
    FROM et25_tmp_media
    GROUP BY aluno_id, turma_id
  ) b
  INNER JOIN alunos a ON a.id = b.aluno_id
  LEFT JOIN (
    SELECT aluno_id, turma_id, SUM(aulas) aulas, SUM(presencas) presencas
    FROM et25_tmp_freq
    GROUP BY aluno_id, turma_id
  ) f ON f.aluno_id = b.aluno_id AND f.turma_id = b.turma_id
  INNER JOIN fechamento_periodo fp ON fp.turma_id = b.turma_id AND fp.periodo_tipo = 'ano' AND fp.periodo_numero = 0 AND fp.vigente = 1 AND fp.vigente_chave LIKE 'ET25-%';

  INSERT INTO resultado_academico_itens (
    resultado_id, materia_id, materia_nome, carga_horaria, media, recuperacao, media_final,
    faltas, frequencia_percentual, situacao, rotulo, ordem
  )
  SELECT r.id, x.materia_id, m.nome, 80, x.media_antes, x.rec, x.media_final,
         NULL, NULL,
         CASE
           WHEN x.media_final >= 6 AND x.media_antes < 6 THEN 'aprovado_recuperacao'
           WHEN x.media_final >= 6 THEN 'aprovado'
           WHEN RIGHT(a.nickname, 2) = '43' AND x.media_final < 6 AND x.media_final >= 5 THEN 'aprovado_conselho'
           ELSE 'reprovado_rendimento'
         END,
         CASE
           WHEN x.media_final >= 6 AND x.media_antes < 6 THEN 'Aprovado após recuperação'
           WHEN x.media_final >= 6 THEN 'Aprovado'
           WHEN RIGHT(a.nickname, 2) = '43' AND x.media_final < 6 AND x.media_final >= 5 THEN 'Aprovado pelo Conselho'
           ELSE 'Reprovado por rendimento'
         END,
         IFNULL(mcc.ordem_boletim, 0)
  FROM resultado_academico r
  INNER JOIN alunos a ON a.id = r.aluno_id AND a.nickname LIKE 'et25.%'
  INNER JOIN et25_tmp_media x ON x.aluno_id = r.aluno_id AND x.turma_id = r.turma_id AND x.periodo = r.periodo_numero
  INNER JOIN materias m ON m.id = x.materia_id
  INNER JOIN turmas t ON t.id = r.turma_id
  LEFT JOIN matrizes_curriculares_componentes mcc ON mcc.matriz_id = t.matriz_curricular_id AND mcc.materia_id = x.materia_id
  WHERE r.periodo_tipo = 'trimestre' AND r.ano_letivo = 2025;

  INSERT INTO resultado_documento_emissoes (
    tipo, modelo_codigo, aluno_id, turma_id, resultado_id, ano_letivo, periodo_tipo, periodo_numero,
    numero, hash_validacao, snapshot_json, emitido_por
  )
  SELECT 'boletim', 'resultado_boletim', r.aluno_id, r.turma_id, r.id, 2025, r.periodo_tipo, r.periodo_numero,
         r.id, SHA2(CONCAT('et25-bol-', r.id), 256),
         JSON_OBJECT('origem','ET25','situacao', r.situacao, 'media', r.media_final, 'frequencia', r.frequencia_percentual),
         v_admin
  FROM resultado_academico r
  INNER JOIN alunos a ON a.id = r.aluno_id AND a.nickname LIKE 'et25.%'
  WHERE r.ano_letivo = 2025;

  INSERT INTO resultado_documento_emissoes (
    tipo, modelo_codigo, aluno_id, turma_id, resultado_id, ano_letivo, periodo_tipo, periodo_numero,
    numero, hash_validacao, snapshot_json, emitido_por
  )
  SELECT 'ficha_individual', 'resultado_ficha_individual', r.aluno_id, r.turma_id, r.id, 2025, 'ano', 0,
         r.id, SHA2(CONCAT('et25-ficha-', r.id), 256),
         JSON_OBJECT('origem','ET25','situacao', r.situacao, 'media', r.media_final),
         v_admin
  FROM resultado_academico r
  INNER JOIN alunos a ON a.id = r.aluno_id AND a.nickname LIKE 'et25.%'
  WHERE r.ano_letivo = 2025 AND r.periodo_tipo = 'ano';

  INSERT INTO resultado_documento_emissoes (
    tipo, modelo_codigo, turma_id, ano_letivo, periodo_tipo, periodo_numero,
    numero, hash_validacao, snapshot_json, emitido_por
  )
  SELECT 'ata', 'resultado_ata', t.id, 2025, 'ano', 0,
         t.id, SHA2(CONCAT('et25-ata-', t.id), 256),
         JSON_OBJECT('origem','ET25','turma', t.nome),
         v_admin
  FROM turmas t
  WHERE t.observacoes LIKE 'ET25 %';

  INSERT INTO historico_documentos (
    aluno_id, unidade_id, versao, status, hash_validacao, finalidade, observacoes_gerais,
    numero_registro_sed, emitido_em, emitido_por
  )
  SELECT a.id, v_unidade, 1, 'Emitido', SHA2(CONCAT('ET25-H-', a.id), 256),
    IF(RIGHT(a.nickname, 2) = '47', 'Transferencia', IF(SUBSTRING(a.nickname, 6, 1) = '3', 'Conclusao', 'Solicitacao')),
    'ET25 histórico escolar emitido para demonstração',
    CONCAT('ET25-', a.id), NOW(), v_admin
  FROM alunos a
  WHERE a.nickname LIKE 'et25.%'
    AND (SUBSTRING(a.nickname, 6, 1) = '3' OR RIGHT(a.nickname, 2) IN ('45','46','47'));

  INSERT INTO historico_itens (
    historico_id, ano_letivo, serie_ano, componente, materia_id, resultado_valor,
    carga_horaria, frequencia_percentual, origem, escola_origem, ordem
  )
  SELECT h.id, ant.ano, ant.serie, m.nome, m.id,
         REPLACE(CAST(ROUND(6 + (CRC32(CONCAT(a.id,'|',m.id,'|',ant.ano)) % 35) / 10, 2) AS CHAR), '.', ','),
         80, 92.00,
         IF(RIGHT(a.nickname, 2) IN ('45','46'), 'Externo', 'Interno'),
         IF(RIGHT(a.nickname, 2) = '46', 'Colégio Santa Luzia', IF(RIGHT(a.nickname, 2) = '45', 'Escola Estadual Dom Pedro II', NULL)),
         mcc.ordem_historico
  FROM historico_documentos h
  INNER JOIN alunos a ON a.id = h.aluno_id AND a.nickname LIKE 'et25.%'
  INNER JOIN (
    SELECT '3' serie_cod, '2023' ano, '1º Ano EM' serie
    UNION ALL SELECT '3', '2024', '2º Ano EM'
    UNION ALL SELECT '2', '2024', '1º Ano EM'
    UNION ALL SELECT '1', '2024', '9º Ano EF'
  ) ant ON ant.serie_cod = SUBSTRING(a.nickname, 6, 1)
    AND (ant.serie_cod <> '1' OR RIGHT(a.nickname, 2) IN ('45','46'))
    AND (ant.serie_cod <> '2' OR SUBSTRING(a.nickname, 6, 1) = '3' OR RIGHT(a.nickname, 2) IN ('45','46'))
  INNER JOIN materias m ON m.nome IN ('Língua Portuguesa','Matemática','História','Geografia','Física','Química','Biologia','Sociologia')
  INNER JOIN turmas t ON t.id = a.turma_id
  LEFT JOIN matrizes_curriculares_componentes mcc ON mcc.matriz_id = t.matriz_curricular_id AND mcc.materia_id = m.id
  WHERE h.numero_registro_sed LIKE 'ET25-%';

  INSERT INTO historico_itens (
    historico_id, ano_letivo, serie_ano, componente, materia_id, resultado_valor,
    carga_horaria, origem, escola_origem, ordem
  )
  SELECT h.id, '2025', CONCAT(a.serie, ' — 1º trimestre (origem)'), m.nome, m.id,
         REPLACE(CAST(ROUND(5 + (CRC32(CONCAT(a.id,'|t1|',m.id)) % 40) / 10, 2) AS CHAR), '.', ','),
         27, 'Externo', 'Colégio Santa Luzia', IFNULL(mcc.ordem_historico, 0)
  FROM historico_documentos h
  INNER JOIN alunos a ON a.id = h.aluno_id AND a.nickname LIKE 'et25.%' AND RIGHT(a.nickname, 2) = '46'
  INNER JOIN materias m ON m.nome IN ('Língua Portuguesa','Matemática','História','Geografia','Física','Química','Biologia','Sociologia')
  INNER JOIN turmas t ON t.id = a.turma_id
  LEFT JOIN matrizes_curriculares_componentes mcc ON mcc.matriz_id = t.matriz_curricular_id AND mcc.materia_id = m.id
  WHERE h.numero_registro_sed LIKE 'ET25-%';

  INSERT INTO historico_itens (
    historico_id, ano_letivo, serie_ano, componente, materia_id, resultado_valor, carga_horaria, origem, ordem
  )
  SELECT h.id, '2025', a.serie, m.nome, x.materia_id,
         REPLACE(CAST(ROUND(AVG(x.media_final), 2) AS CHAR), '.', ','),
         80, 'Interno', IFNULL(MIN(mcc.ordem_historico), 0)
  FROM historico_documentos h
  INNER JOIN alunos a ON a.id = h.aluno_id AND a.nickname LIKE 'et25.%'
  INNER JOIN et25_tmp_media x ON x.aluno_id = a.id
  INNER JOIN materias m ON m.id = x.materia_id
  INNER JOIN turmas t ON t.id = a.turma_id
  LEFT JOIN matrizes_curriculares_componentes mcc ON mcc.matriz_id = t.matriz_curricular_id AND mcc.materia_id = x.materia_id
  WHERE h.numero_registro_sed LIKE 'ET25-%'
  GROUP BY h.id, a.serie, m.nome, x.materia_id;

  INSERT INTO historico_resultados_anuais (historico_id, ano_letivo, serie_ano, resultado, observacao)
  SELECT h.id, i.ano_letivo, i.serie_ano,
    IF(i.ano_letivo = '2025' AND RIGHT(a.nickname, 2) = '47', 'Transferido',
      IF(i.ano_letivo = '2025' AND RIGHT(a.nickname, 2) = '48', 'Evadido',
        IF(i.ano_letivo = '2025', 'Cursando', 'Aprovado'))),
    'ET25'
  FROM historico_documentos h
  INNER JOIN alunos a ON a.id = h.aluno_id
  INNER JOIN (
    SELECT DISTINCT historico_id, ano_letivo, serie_ano FROM historico_itens
  ) i ON i.historico_id = h.id
  WHERE h.numero_registro_sed LIKE 'ET25-%';

  INSERT INTO historico_assinaturas (historico_id, usuario_id, usuario_nome, cargo, numero_registro, tipo)
  SELECT h.id, v_admin, 'Helena Duarte', 'Diretor', 'RG 12.345.678-9 SSP/SP', 'Eletronica_Simples'
  FROM historico_documentos h
  WHERE h.numero_registro_sed LIKE 'ET25-%';

  INSERT INTO resultado_documento_emissoes (
    tipo, aluno_id, turma_id, ano_letivo, periodo_tipo, periodo_numero, numero, hash_validacao, snapshot_json, emitido_por
  )
  SELECT IF(RIGHT(a.nickname, 2) = '47', 'historico_transferencia', 'historico'),
         a.id, a.turma_id, 2025, 'ano', 0, h.id, h.hash_validacao,
         JSON_OBJECT('origem','ET25','finalidade', h.finalidade, 'numero', h.numero_registro_sed),
         v_admin
  FROM historico_documentos h
  INNER JOIN alunos a ON a.id = h.aluno_id
  WHERE h.numero_registro_sed LIKE 'ET25-%';

  INSERT INTO planos_aula (professor_id, materia_id, turma_id, data_aula, titulo, ano_disciplina, objetivos, conteudo, metodologia, avaliacao, status)
  SELECT g.professor_id, g.materia_id, g.turma_id,
         ELT(b.bim, '2025-02-03','2025-05-19','2025-09-01'),
         CONCAT('ET25 ', t.nome, ' ', m.nome, ' T', b.bim),
         CONCAT(t.serie, ' / ', m.nome),
         CONCAT('ET25 Desenvolver as habilidades de ', m.nome, ' no ', b.bim, 'º trimestre.'),
         CONCAT('ET25 Sequência alinhada à matriz curricular de ', t.nome, '.'),
         'Exposição dialogada, exercícios e registro no diário.',
         'Simulado 1, Simulado 2, Simulado 3, Prova Bimestral e Rec quando a média ficar abaixo de 6,00.',
         'aprovado'
  FROM (SELECT DISTINCT professor_id, materia_id, turma_id FROM grade_horaria) g
  INNER JOIN turmas t ON t.id = g.turma_id AND t.observacoes LIKE 'ET25 %'
  INNER JOIN materias m ON m.id = g.materia_id
  CROSS JOIN (SELECT 1 bim UNION ALL SELECT 2 UNION ALL SELECT 3) b;

  UPDATE diario_aulas da
  INNER JOIN planos_aula pa ON pa.professor_id = da.professor_id AND pa.turma_id = da.turma_id AND pa.materia_id = da.materia_id
    AND pa.titulo LIKE 'ET25 %' AND pa.deleted_at IS NULL
    AND ((da.data_aula <= '2025-05-16' AND pa.titulo LIKE '% T1')
      OR (da.data_aula BETWEEN '2025-05-19' AND '2025-08-29' AND pa.titulo LIKE '% T2')
      OR (da.data_aula >= '2025-09-01' AND pa.titulo LIKE '% T3'))
  SET da.plano_aula_id = pa.id
  WHERE da.observacoes LIKE 'ET25-SEED%';

  INSERT IGNORE INTO presenca_eventos (aluno_id, tipo, ocorrido_em, origem, id_externo, identificador_bruto, processado_em)
  SELECT a.id, 'entrada', TIMESTAMP(d.data_aula, '07:05:00'),
         IF(a.id % 5 = 0, 'manual_secretaria', 'facial'),
         CONCAT('et25-', a.id, '-', d.data_aula, '-entrada'), a.ra, NOW()
  FROM alunos a
  INNER JOIN (
    SELECT DISTINCT turma_id, data_aula FROM diario_aulas WHERE observacoes LIKE 'ET25-SEED%'
  ) d ON d.turma_id = a.turma_id
  INNER JOIN matricula mt ON mt.aluno_id = a.id AND mt.turma_id = a.turma_id
  WHERE a.nickname LIKE 'et25.%'
    AND d.data_aula >= mt.data_entrada
    AND (mt.data_saida IS NULL OR d.data_aula <= mt.data_saida)
    AND EXISTS (
      SELECT 1 FROM diario_frequencias df
      INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
      WHERE df.aluno_id = a.id AND da.data_aula = d.data_aula AND da.turma_id = a.turma_id
        AND df.situacao IN ('presente','atraso','saida_antecipada','falta_justificada')
    );

  DROP TABLE IF EXISTS et25_tmp_media;
  DROP TABLE IF EXISTS et25_tmp_freq;

  IF (SELECT COUNT(*) FROM alunos WHERE nickname LIKE 'et25.%') <> 600 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Seed ET25 incompleto: esperado 600 alunos.';
  END IF;
END$$

DELIMITER ;

CALL seed_escola_teste_em_2025();
DROP PROCEDURE IF EXISTS seed_escola_teste_em_2025;
DROP PROCEDURE IF EXISTS apagar_escola_teste_em_2025;
