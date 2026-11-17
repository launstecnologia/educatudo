-- =============================================================================
-- Carga pontual — frequência, ocorrências e quantidade de faltas de 2024.
-- Só a aluna Ana Luiza Oliveira De Souza, turma 1A Santa Maria.
-- Não cria ano, turma, aluna, notas nem boletim. Eles precisam já existir.
--
-- Master → Migrations → Colégio Educa → Escolher → marque SÓ este arquivo.
-- Não entra em "Executar todas" (nome importar_dados_*).
-- Pode rodar de novo: apaga a própria carga e recria.
-- Para só esvaziar: 2026_11_16_importar_dados_santa_maria_ana_2024_frequencia_apagar.sql
-- O DELETE abaixo só remove esta carga (marcador SM24 frequência 2024 / títulos
-- destas ocorrências / lançamentos SM24 Faltas desta aluna). Não apaga o boletim.
-- =============================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '-03:00';

DROP PROCEDURE IF EXISTS apagar_sm24_frequencia_ana;
DROP PROCEDURE IF EXISTS seed_sm24_frequencia_ana;

DELIMITER $$

CREATE PROCEDURE apagar_sm24_frequencia_ana()
BEGIN
  DECLARE v_aluno INT DEFAULT 0;
  DECLARE v_turma INT DEFAULT 0;

  SELECT COALESCE(MAX(id), 0) INTO v_aluno FROM (
    SELECT a.id
      FROM matricula m
      INNER JOIN alunos a ON a.id = m.aluno_id
      INNER JOIN turmas t ON t.id = m.turma_id
      INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
     WHERE al.ano = 2024
       AND t.nome = '1A Santa Maria'
       AND m.status IN ('ativa', 'concluido')
       AND (a.nome = 'Ana Luiza Oliveira De Souza' OR a.ra = '24254' OR a.nickname = 'sm24.analuiza')
     LIMIT 1
  ) achou;

  IF v_aluno = 0 THEN
    SELECT COALESCE(MAX(id), 0) INTO v_aluno FROM (
      SELECT a.id
        FROM alunos a
        INNER JOIN turmas t ON t.id = a.turma_id
        LEFT JOIN ano_letivo al ON al.id = t.ano_letivo_id
       WHERE t.nome = '1A Santa Maria'
         AND (t.ano_letivo = 2024 OR al.ano = 2024)
         AND (a.nome = 'Ana Luiza Oliveira De Souza' OR a.ra = '24254' OR a.nickname = 'sm24.analuiza')
       LIMIT 1
    ) achou;
  END IF;

  SELECT COALESCE(MAX(turma_id), 0) INTO v_turma FROM alunos WHERE id = v_aluno;

  IF v_aluno > 0 THEN
    DELETE l FROM faltas_lancamentos l
    INNER JOIN faltas_eventos e ON e.id = l.evento_id
    WHERE l.aluno_id = v_aluno
      AND e.nome IN (
        'SM24 Faltas 1º bimestre 2024',
        'SM24 Faltas 2º bimestre 2024',
        'SM24 Faltas 3º bimestre 2024',
        'SM24 Faltas 4º bimestre 2024'
      );

    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ocorrencias_anexos') > 0 THEN
      DELETE ax FROM ocorrencias_anexos ax
      INNER JOIN alunos_ocorrencias o ON o.id = ax.ocorrencia_id
      WHERE o.aluno_id = v_aluno
        AND o.titulo IN (
          'Atraso na entrada',
          'Conversa durante a explicação',
          'Dificuldade em Matemática',
          'Esqueceu o material',
          'Participação em Geografia'
        );
    END IF;

    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ocorrencias_historico') > 0 THEN
      DELETE h FROM ocorrencias_historico h
      INNER JOIN alunos_ocorrencias o ON o.id = h.ocorrencia_id
      WHERE o.aluno_id = v_aluno
        AND o.titulo IN (
          'Atraso na entrada',
          'Conversa durante a explicação',
          'Dificuldade em Matemática',
          'Esqueceu o material',
          'Participação em Geografia'
        );
    END IF;

    DELETE i FROM alunos_ocorrencias_itens i
    INNER JOIN alunos_ocorrencias o ON o.id = i.ocorrencia_id
    WHERE o.aluno_id = v_aluno
      AND o.titulo IN (
        'Atraso na entrada',
        'Conversa durante a explicação',
        'Dificuldade em Matemática',
        'Esqueceu o material',
        'Participação em Geografia'
      );

    DELETE FROM alunos_ocorrencias
     WHERE aluno_id = v_aluno
       AND titulo IN (
         'Atraso na entrada',
         'Conversa durante a explicação',
         'Dificuldade em Matemática',
         'Esqueceu o material',
         'Participação em Geografia'
       );
  END IF;

  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'diario_frequencias') > 0
     AND v_aluno > 0 THEN
    DELETE df FROM diario_frequencias df
    INNER JOIN diario_aulas da ON da.id = df.diario_aula_id
    WHERE df.aluno_id = v_aluno
      AND df.origem = 'importacao'
      AND da.data_aula BETWEEN '2024-01-01' AND '2024-12-31'
      AND (v_turma = 0 OR da.turma_id = v_turma);
  END IF;

  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'diario_aulas') > 0 THEN
    DELETE FROM diario_aulas WHERE conteudo_realizado = 'SM24 frequência 2024';
  END IF;

  IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presenca_eventos') > 0 THEN
    DELETE FROM presenca_eventos WHERE id_externo LIKE 'SM24-ana-%';
  END IF;
END$$

CREATE PROCEDURE seed_sm24_frequencia_ana()
BEGIN
  DECLARE v_admin INT DEFAULT 0;
  DECLARE v_aluno INT DEFAULT 0;
  DECLARE v_turma INT DEFAULT 0;
  DECLARE v_ano INT DEFAULT 0;
  DECLARE v_prof INT DEFAULT 0;
  DECLARE v_oc INT DEFAULT 0;

  SELECT COALESCE(MAX(id), 0) INTO v_admin FROM (
    SELECT id FROM usuarios
     WHERE (email LIKE 'admin@%' OR tipo = 'admin_escola') AND COALESCE(ativo, 1) = 1
     ORDER BY id
     LIMIT 1
  ) adm;

  SELECT COALESCE(MAX(id), 0) INTO v_aluno FROM (
    SELECT a.id
      FROM matricula m
      INNER JOIN alunos a ON a.id = m.aluno_id
      INNER JOIN turmas t ON t.id = m.turma_id
      INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
     WHERE al.ano = 2024
       AND t.nome = '1A Santa Maria'
       AND m.status IN ('ativa', 'concluido')
       AND (a.nome = 'Ana Luiza Oliveira De Souza' OR a.ra = '24254' OR a.nickname = 'sm24.analuiza')
     LIMIT 1
  ) achou;

  IF v_aluno = 0 THEN
    SELECT COALESCE(MAX(id), 0) INTO v_aluno FROM (
      SELECT a.id
        FROM alunos a
        INNER JOIN turmas t ON t.id = a.turma_id
        LEFT JOIN ano_letivo al ON al.id = t.ano_letivo_id
       WHERE t.nome = '1A Santa Maria'
         AND (t.ano_letivo = 2024 OR al.ano = 2024)
         AND (a.nome = 'Ana Luiza Oliveira De Souza' OR a.ra = '24254' OR a.nickname = 'sm24.analuiza')
       LIMIT 1
    ) achou;
  END IF;

  IF v_aluno = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Ana Luiza não está na turma 1A Santa Maria de 2024. Crie o ano, a turma e a aluna antes desta carga.';
  END IF;

  IF v_admin = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Nenhum admin ativo nesta escola.';
  END IF;

  SELECT COALESCE(MAX(id), 0) INTO v_turma FROM (
    SELECT t.id
      FROM matricula m
      INNER JOIN turmas t ON t.id = m.turma_id
      INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id
     WHERE m.aluno_id = v_aluno
       AND al.ano = 2024
       AND t.nome = '1A Santa Maria'
       AND m.status IN ('ativa', 'concluido')
     LIMIT 1
  ) turma_mat;

  IF v_turma = 0 THEN
    SELECT COALESCE(turma_id, 0) INTO v_turma FROM alunos WHERE id = v_aluno;
  END IF;

  SELECT COALESCE(MAX(id), 0) INTO v_ano FROM ano_letivo WHERE ano = 2024;

  IF v_turma = 0 OR v_ano = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Ano letivo 2024 ou turma 1A Santa Maria não encontrados.';
  END IF;

  SELECT COALESCE(MAX(id), 0) INTO v_prof FROM (
    SELECT id FROM professores ORDER BY id LIMIT 1
  ) prof;

  DROP TEMPORARY TABLE IF EXISTS sm24f_mat;
  CREATE TEMPORARY TABLE sm24f_mat (
    materia_id INT NOT NULL,
    nome VARCHAR(120) NOT NULL,
    b1 INT NOT NULL,
    b2 INT NOT NULL,
    b3 INT NOT NULL,
    b4 INT NOT NULL,
    PRIMARY KEY (materia_id)
  );

  INSERT INTO sm24f_mat (materia_id, nome, b1, b2, b3, b4)
  SELECT m.id, nomes.nome, nomes.b1, nomes.b2, nomes.b3, nomes.b4
    FROM (
      SELECT 'Língua Portuguesa' AS nome, 0 AS b1, 0 AS b2, 2 AS b3, 0 AS b4
      UNION ALL SELECT 'Redação', 0, 0, 2, 0
      UNION ALL SELECT 'Literatura', 0, 0, 1, 0
      UNION ALL SELECT 'Arte', 0, 0, 1, 0
      UNION ALL SELECT 'História 1', 0, 0, 2, 0
      UNION ALL SELECT 'História 2', 0, 0, 4, 0
      UNION ALL SELECT 'Geografia', 0, 0, 3, 0
      UNION ALL SELECT 'Sociologia', 0, 0, 2, 0
      UNION ALL SELECT 'Filosofia', 0, 0, 2, 0
      UNION ALL SELECT 'Matemática 1', 0, 0, 4, 0
      UNION ALL SELECT 'Matemática 2', 0, 0, 2, 0
      UNION ALL SELECT 'Física 1', 0, 0, 2, 0
      UNION ALL SELECT 'Física 2', 0, 0, 2, 0
      UNION ALL SELECT 'Química 1', 0, 0, 2, 0
      UNION ALL SELECT 'Química 2', 0, 0, 4, 0
      UNION ALL SELECT 'Biologia 1', 0, 0, 5, 0
      UNION ALL SELECT 'Biologia 2', 0, 0, 2, 0
      UNION ALL SELECT 'Língua Inglesa', 0, 0, 2, 0
      UNION ALL SELECT 'Educação Física', 0, 0, 1, 0
    ) nomes
    INNER JOIN materias m ON m.id = (
      SELECT m2.id FROM materias m2
       WHERE m2.nome = nomes.nome
       ORDER BY (CASE WHEN m2.codigo LIKE 'SM24-%' THEN 1 ELSE 0 END), m2.id
       LIMIT 1
    );

  IF (SELECT COUNT(*) FROM sm24f_mat) = 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Nenhum componente do boletim 2024 foi encontrado (Língua Portuguesa, Matemática 1 e os demais).';
  END IF;

  DROP TEMPORARY TABLE IF EXISTS sm24f_ev_tem;
  CREATE TEMPORARY TABLE sm24f_ev_tem AS
  SELECT nome FROM faltas_eventos
   WHERE nome IN (
     'SM24 Faltas 1º bimestre 2024',
     'SM24 Faltas 2º bimestre 2024',
     'SM24 Faltas 3º bimestre 2024',
     'SM24 Faltas 4º bimestre 2024'
   );

  INSERT INTO faltas_eventos (nome, bimestre, ano_letivo, turmas_json, origem, created_by, ativo)
  SELECT ev.nome, CAST(ev.bim AS CHAR), 2024, JSON_ARRAY(v_turma), 'manual', v_admin, 1
    FROM (
      SELECT 1 AS bim, 'SM24 Faltas 1º bimestre 2024' AS nome
      UNION ALL SELECT 2, 'SM24 Faltas 2º bimestre 2024'
      UNION ALL SELECT 3, 'SM24 Faltas 3º bimestre 2024'
      UNION ALL SELECT 4, 'SM24 Faltas 4º bimestre 2024'
    ) ev
   WHERE NOT EXISTS (
     SELECT 1 FROM sm24f_ev_tem t WHERE t.nome = ev.nome
   );
  DROP TEMPORARY TABLE IF EXISTS sm24f_ev_tem;

  INSERT INTO faltas_lancamentos (evento_id, aluno_id, materia_id, faltas, created_by)
  SELECT e.id, v_aluno, mat.materia_id,
         CASE ev.bim WHEN 1 THEN mat.b1 WHEN 2 THEN mat.b2 WHEN 3 THEN mat.b3 ELSE mat.b4 END,
         v_admin
    FROM sm24f_mat mat
    INNER JOIN (
      SELECT 1 AS bim, 'SM24 Faltas 1º bimestre 2024' AS nome
      UNION ALL SELECT 2, 'SM24 Faltas 2º bimestre 2024'
      UNION ALL SELECT 3, 'SM24 Faltas 3º bimestre 2024'
      UNION ALL SELECT 4, 'SM24 Faltas 4º bimestre 2024'
    ) ev
    INNER JOIN faltas_eventos e ON e.nome = ev.nome AND e.ativo = 1 AND e.ano_letivo = 2024
  ON DUPLICATE KEY UPDATE faltas = VALUES(faltas), created_by = VALUES(created_by);

  INSERT INTO ocorrencias_categorias (slug, nome, ordem, ativo)
  SELECT c.slug, c.nome, c.ordem, 1
    FROM (
      SELECT 'pedagogica' AS slug, 'Pedagógica' AS nome, 1 AS ordem
      UNION ALL SELECT 'comportamento', 'Comportamento / convivência', 2
      UNION ALL SELECT 'atraso', 'Atraso', 3
      UNION ALL SELECT 'material', 'Material', 5
      UNION ALL SELECT 'elogio', 'Elogio', 7
    ) c
   WHERE NOT EXISTS (SELECT 1 FROM ocorrencias_categorias x WHERE x.slug = c.slug);

  INSERT INTO alunos_ocorrencias (
    aluno_id, categoria_id, data_ocorrencia, titulo, detalhe, nivel_gravidade, status,
    turma_id, ano_letivo_id, materia_id, local, retorno_em, enviar_pais,
    responsavel_comunicado_em, encerrado_em, encerrado_por, criado_por
  ) VALUES (
    v_aluno,
    (SELECT id FROM ocorrencias_categorias WHERE slug = 'atraso' LIMIT 1),
    '2024-03-12 07:42:00',
    'Atraso na entrada',
    'Ana Luiza chegou após o início da primeira aula. A família foi comunicada.',
    'leve', 'encerrada', v_turma, v_ano, NULL, 'Portaria', NULL, 1,
    '2024-03-12 07:42:00', '2024-03-12 07:42:00', v_admin, v_admin
  );
  SET v_oc = LAST_INSERT_ID();
  INSERT INTO alunos_ocorrencias_itens (ocorrencia_id, aluno_id) VALUES (v_oc, v_aluno);
  INSERT INTO ocorrencias_historico (ocorrencia_id, usuario_id, acao, motivo)
  VALUES (v_oc, v_admin, 'criou', NULL), (v_oc, v_admin, 'encerrou', 'Fechamento da carga de teste 2024');

  INSERT INTO alunos_ocorrencias (
    aluno_id, categoria_id, data_ocorrencia, titulo, detalhe, nivel_gravidade, status,
    turma_id, ano_letivo_id, materia_id, local, retorno_em, enviar_pais,
    responsavel_comunicado_em, criado_por
  ) VALUES (
    v_aluno,
    (SELECT id FROM ocorrencias_categorias WHERE slug = 'comportamento' LIMIT 1),
    '2024-05-20 09:15:00',
    'Conversa durante a explicação',
    'Conversou durante a explicação de História e recebeu advertência verbal da coordenação.',
    'moderado', 'em_acompanhamento', v_turma, v_ano,
    (SELECT MAX(materia_id) FROM sm24f_mat WHERE nome = 'História 1'),
    'Sala de aula', '2024-06-03', 1, '2024-05-20 09:15:00', v_admin
  );
  SET v_oc = LAST_INSERT_ID();
  INSERT INTO alunos_ocorrencias_itens (ocorrencia_id, aluno_id) VALUES (v_oc, v_aluno);
  INSERT INTO ocorrencias_historico (ocorrencia_id, usuario_id, acao, motivo)
  VALUES (v_oc, v_admin, 'criou', NULL), (v_oc, v_admin, 'acompanhou', 'Fechamento da carga de teste 2024');

  INSERT INTO alunos_ocorrencias (
    aluno_id, categoria_id, data_ocorrencia, titulo, detalhe, nivel_gravidade, status,
    turma_id, ano_letivo_id, materia_id, local, retorno_em, enviar_pais,
    responsavel_comunicado_em, criado_por
  ) VALUES (
    v_aluno,
    (SELECT id FROM ocorrencias_categorias WHERE slug = 'pedagogica' LIMIT 1),
    '2024-08-19 10:00:00',
    'Dificuldade em Matemática',
    'A coordenação registrou dificuldade recorrente em Matemática no 3º bimestre e combinou acompanhamento.',
    'leve', 'aberta', v_turma, v_ano,
    (SELECT MAX(materia_id) FROM sm24f_mat WHERE nome = 'Matemática 1'),
    'Coordenação', '2024-09-02', 1, '2024-08-19 10:00:00', v_admin
  );
  SET v_oc = LAST_INSERT_ID();
  INSERT INTO alunos_ocorrencias_itens (ocorrencia_id, aluno_id) VALUES (v_oc, v_aluno);
  INSERT INTO ocorrencias_historico (ocorrencia_id, usuario_id, acao) VALUES (v_oc, v_admin, 'criou');

  INSERT INTO alunos_ocorrencias (
    aluno_id, categoria_id, data_ocorrencia, titulo, detalhe, nivel_gravidade, status,
    turma_id, ano_letivo_id, materia_id, local, enviar_pais,
    responsavel_comunicado_em, encerrado_em, encerrado_por, criado_por
  ) VALUES (
    v_aluno,
    (SELECT id FROM ocorrencias_categorias WHERE slug = 'material' LIMIT 1),
    '2024-09-04 08:10:00',
    'Esqueceu o material',
    'Veio sem o material de Educação Física. Orientada a trazer na aula seguinte.',
    'leve', 'encerrada', v_turma, v_ano,
    (SELECT MAX(materia_id) FROM sm24f_mat WHERE nome = 'Educação Física'),
    'Quadra', 1, '2024-09-04 08:10:00', '2024-09-04 08:10:00', v_admin, v_admin
  );
  SET v_oc = LAST_INSERT_ID();
  INSERT INTO alunos_ocorrencias_itens (ocorrencia_id, aluno_id) VALUES (v_oc, v_aluno);
  INSERT INTO ocorrencias_historico (ocorrencia_id, usuario_id, acao, motivo)
  VALUES (v_oc, v_admin, 'criou', NULL), (v_oc, v_admin, 'encerrou', 'Fechamento da carga de teste 2024');

  INSERT INTO alunos_ocorrencias (
    aluno_id, categoria_id, data_ocorrencia, titulo, detalhe, nivel_gravidade, status,
    turma_id, ano_letivo_id, materia_id, local, enviar_pais,
    responsavel_comunicado_em, encerrado_em, encerrado_por, criado_por
  ) VALUES (
    v_aluno,
    (SELECT id FROM ocorrencias_categorias WHERE slug = 'elogio' LIMIT 1),
    '2024-09-18 11:00:00',
    'Participação em Geografia',
    'Destaque na apresentação do trabalho de Geografia. Elogio registrado para a família.',
    'leve', 'encerrada', v_turma, v_ano,
    (SELECT MAX(materia_id) FROM sm24f_mat WHERE nome = 'Geografia'),
    'Sala de aula', 1, '2024-09-18 11:00:00', '2024-09-18 11:00:00', v_admin, v_admin
  );
  SET v_oc = LAST_INSERT_ID();
  INSERT INTO alunos_ocorrencias_itens (ocorrencia_id, aluno_id) VALUES (v_oc, v_aluno);
  INSERT INTO ocorrencias_historico (ocorrencia_id, usuario_id, acao, motivo)
  VALUES (v_oc, v_admin, 'criou', NULL), (v_oc, v_admin, 'encerrou', 'Fechamento da carga de teste 2024');

  IF v_prof > 0
     AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'diario_aulas') > 0 THEN

    DROP TEMPORARY TABLE IF EXISTS sm24f_sem_grade;
    CREATE TEMPORARY TABLE sm24f_sem_grade AS
    SELECT materia_id,
           ((ROW_NUMBER() OVER (ORDER BY materia_id) - 1) % 5) + 1 AS dia,
           (FLOOR((ROW_NUMBER() OVER (ORDER BY materia_id) - 1) / 5) % 4) + 1 AS faixa
      FROM sm24f_mat mat
     WHERE NOT EXISTS (
       SELECT 1 FROM grade_horaria g
        WHERE g.turma_id = v_turma AND g.materia_id = mat.materia_id
     );

    INSERT INTO grade_horaria (dia_semana, horario_de, horario_ate, turma_id, professor_id, materia_id, periodo)
    SELECT dia,
           ELT(CAST(faixa AS UNSIGNED), '07:30:00', '08:20:00', '09:30:00', '10:20:00'),
           ELT(CAST(faixa AS UNSIGNED), '08:20:00', '09:10:00', '10:20:00', '11:10:00'),
           v_turma, v_prof, materia_id, 'manha'
      FROM sm24f_sem_grade;
    DROP TEMPORARY TABLE IF EXISTS sm24f_sem_grade;

    DROP TEMPORARY TABLE IF EXISTS sm24f_slot;
    CREATE TEMPORARY TABLE sm24f_slot AS
    SELECT mat.materia_id, g.id AS grade_id, g.professor_id, g.dia_semana,
           g.horario_de, g.horario_ate, mat.b1, mat.b2, mat.b3, mat.b4
      FROM sm24f_mat mat
      INNER JOIN grade_horaria g ON g.id = (
        SELECT g2.id FROM grade_horaria g2
         WHERE g2.turma_id = v_turma AND g2.materia_id = mat.materia_id
         ORDER BY g2.id
         LIMIT 1
      );

    DROP TEMPORARY TABLE IF EXISTS sm24f_slot2;
    CREATE TEMPORARY TABLE sm24f_slot2 AS SELECT * FROM sm24f_slot;

    DROP TEMPORARY TABLE IF EXISTS sm24f_per;
    CREATE TEMPORARY TABLE sm24f_per (
      numero TINYINT NOT NULL PRIMARY KEY,
      inicio DATE NOT NULL,
      fim DATE NOT NULL
    );
    INSERT INTO sm24f_per (numero, inicio, fim) VALUES
      (1, '2024-02-01', '2024-04-15'),
      (2, '2024-04-16', '2024-06-30'),
      (3, '2024-08-01', '2024-10-15'),
      (4, '2024-10-16', '2024-12-20');

    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ano_letivo_periodos') > 0 THEN
      DELETE p FROM sm24f_per p
      INNER JOIN ano_letivo_periodos ap ON ap.numero = p.numero
      INNER JOIN ano_letivo a ON a.id = ap.ano_letivo_id AND a.ano = 2024
      WHERE ap.data_inicio IS NOT NULL AND ap.data_fim IS NOT NULL;

      INSERT INTO sm24f_per (numero, inicio, fim)
      SELECT ap.numero, ap.data_inicio, ap.data_fim
        FROM ano_letivo_periodos ap
        INNER JOIN ano_letivo a ON a.id = ap.ano_letivo_id
       WHERE a.ano = 2024
         AND ap.numero BETWEEN 1 AND 4
         AND ap.data_inicio IS NOT NULL
         AND ap.data_fim IS NOT NULL;
    END IF;

    DROP TEMPORARY TABLE IF EXISTS sm24f_dias;
    CREATE TEMPORARY TABLE sm24f_dias (d DATE NOT NULL PRIMARY KEY);
    INSERT INTO sm24f_dias (d)
    WITH RECURSIVE seq AS (
      SELECT DATE('2024-02-01') AS d
      UNION ALL
      SELECT DATE_ADD(d, INTERVAL 1 DAY) FROM seq WHERE d < '2024-12-20'
    )
    SELECT d FROM seq;

    DROP TEMPORARY TABLE IF EXISTS sm24f_plano;
    CREATE TEMPORARY TABLE sm24f_plano AS
    SELECT s.grade_id, s.professor_id, s.materia_id, s.horario_de, s.horario_ate,
           x.d, x.rn,
           CASE x.numero WHEN 1 THEN s.b1 WHEN 2 THEN s.b2 WHEN 3 THEN s.b3 ELSE s.b4 END AS faltas_bim
      FROM sm24f_slot s
      INNER JOIN (
        SELECT s2.materia_id, d.d, p.numero,
               ROW_NUMBER() OVER (PARTITION BY s2.materia_id, p.numero ORDER BY d.d) AS rn
          FROM sm24f_slot2 s2
          INNER JOIN sm24f_dias d ON (WEEKDAY(d.d) + 1) = s2.dia_semana
          INNER JOIN sm24f_per p ON d.d BETWEEN p.inicio AND p.fim
      ) x ON x.materia_id = s.materia_id
     WHERE x.rn <= 6;

    DROP TEMPORARY TABLE IF EXISTS sm24f_aulas_existentes;
    CREATE TEMPORARY TABLE sm24f_aulas_existentes AS
    SELECT da.grade_horaria_id, da.data_aula
      FROM diario_aulas da
      INNER JOIN sm24f_plano p ON p.grade_id = da.grade_horaria_id AND p.d = da.data_aula;

    INSERT INTO diario_aulas (
      grade_horaria_id, professor_id, turma_id, materia_id, data_aula,
      horario_de, horario_ate, execucao, conteudo_realizado, status, finalizada_at
    )
    SELECT p.grade_id, p.professor_id, v_turma, p.materia_id, p.d,
           p.horario_de, p.horario_ate, 'conforme_planejado', 'SM24 frequência 2024', 'finalizada', NOW()
      FROM sm24f_plano p
     WHERE NOT EXISTS (
       SELECT 1 FROM sm24f_aulas_existentes e
        WHERE e.grade_horaria_id = p.grade_id AND e.data_aula = p.d
     );
    DROP TEMPORARY TABLE IF EXISTS sm24f_aulas_existentes;

    INSERT INTO diario_frequencias (diario_aula_id, aluno_id, situacao, observacao, origem)
    SELECT da.id, v_aluno,
           IF(p.rn <= p.faltas_bim, 'falta', 'presente'),
           IF(p.rn <= p.faltas_bim, 'Falta do fechamento de 2024.', NULL),
           'importacao'
      FROM sm24f_plano p
      INNER JOIN diario_aulas da ON da.grade_horaria_id = p.grade_id AND da.data_aula = p.d
    ON DUPLICATE KEY UPDATE
      situacao = VALUES(situacao),
      observacao = VALUES(observacao),
      origem = 'importacao';

    IF (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'presenca_eventos') > 0 THEN
      DROP TEMPORARY TABLE IF EXISTS sm24f_pres;
      CREATE TEMPORARY TABLE sm24f_pres AS
      SELECT DISTINCT p.d
        FROM sm24f_plano p
       WHERE p.rn > p.faltas_bim;

      DROP TEMPORARY TABLE IF EXISTS sm24f_pres_tem;
      CREATE TEMPORARY TABLE sm24f_pres_tem AS
      SELECT id_externo FROM presenca_eventos
       WHERE id_externo LIKE CONCAT('SM24-ana-', v_aluno, '-%');

      INSERT INTO presenca_eventos (
        aluno_id, tipo, ocorrido_em, origem, id_externo, identificador_bruto, registrado_por, processado_em
      )
      SELECT v_aluno, 'entrada', TIMESTAMP(dias.d, '07:18:00'), 'importacao',
             CONCAT('SM24-ana-', v_aluno, '-', DATE_FORMAT(dias.d, '%Y-%m-%d'), '-entrada'),
             '24254', v_admin, NOW()
        FROM sm24f_pres dias
       WHERE NOT EXISTS (
         SELECT 1 FROM sm24f_pres_tem t
          WHERE t.id_externo = CONCAT('SM24-ana-', v_aluno, '-', DATE_FORMAT(dias.d, '%Y-%m-%d'), '-entrada')
       );

      INSERT INTO presenca_eventos (
        aluno_id, tipo, ocorrido_em, origem, id_externo, identificador_bruto, registrado_por, processado_em
      )
      SELECT v_aluno, 'saida', TIMESTAMP(dias.d, '12:05:00'), 'importacao',
             CONCAT('SM24-ana-', v_aluno, '-', DATE_FORMAT(dias.d, '%Y-%m-%d'), '-saida'),
             '24254', v_admin, NOW()
        FROM sm24f_pres dias
       WHERE NOT EXISTS (
         SELECT 1 FROM sm24f_pres_tem t
          WHERE t.id_externo = CONCAT('SM24-ana-', v_aluno, '-', DATE_FORMAT(dias.d, '%Y-%m-%d'), '-saida')
       );

      DROP TEMPORARY TABLE IF EXISTS sm24f_pres_tem;
      DROP TEMPORARY TABLE IF EXISTS sm24f_pres;
    END IF;

    DROP TEMPORARY TABLE IF EXISTS sm24f_plano;
    DROP TEMPORARY TABLE IF EXISTS sm24f_dias;
    DROP TEMPORARY TABLE IF EXISTS sm24f_per;
    DROP TEMPORARY TABLE IF EXISTS sm24f_slot2;
    DROP TEMPORARY TABLE IF EXISTS sm24f_slot;
  END IF;

  DROP TEMPORARY TABLE IF EXISTS sm24f_mat;
END$$

DELIMITER ;

CALL apagar_sm24_frequencia_ana();
CALL seed_sm24_frequencia_ana();
DROP PROCEDURE IF EXISTS seed_sm24_frequencia_ana;
DROP PROCEDURE IF EXISTS apagar_sm24_frequencia_ana;
