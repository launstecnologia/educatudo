-- ============================================================
-- SEED DEMO — Ensino Médio completo
-- Execute APENAS no tenant "demo" via painel Master
-- Idempotente: usa INSERT IGNORE / ON DUPLICATE KEY UPDATE
-- ============================================================

DROP PROCEDURE IF EXISTS seed_demo_ensino_medio;

DELIMITER $$

CREATE PROCEDURE seed_demo_ensino_medio()
BEGIN
    -- ── Variáveis de IDs principais ─────────────────────────
    DECLARE v_ano_letivo_id   INT DEFAULT 0;
    DECLARE v_curso_id        INT DEFAULT 0;

    -- Series
    DECLARE v_serie1_id INT DEFAULT 0;
    DECLARE v_serie2_id INT DEFAULT 0;
    DECLARE v_serie3_id INT DEFAULT 0;

    -- Turmas
    DECLARE v_turma1_id INT DEFAULT 0;
    DECLARE v_turma2_id INT DEFAULT 0;
    DECLARE v_turma3_id INT DEFAULT 0;

    -- Matérias
    DECLARE v_mat_port INT DEFAULT 0;
    DECLARE v_mat_mat  INT DEFAULT 0;
    DECLARE v_mat_fis  INT DEFAULT 0;
    DECLARE v_mat_qui  INT DEFAULT 0;
    DECLARE v_mat_bio  INT DEFAULT 0;
    DECLARE v_mat_his  INT DEFAULT 0;
    DECLARE v_mat_geo  INT DEFAULT 0;

    -- Professores
    DECLARE v_prof_port INT DEFAULT 0;
    DECLARE v_prof_mat  INT DEFAULT 0;
    DECLARE v_prof_fis  INT DEFAULT 0;
    DECLARE v_prof_qui  INT DEFAULT 0;
    DECLARE v_prof_bio  INT DEFAULT 0;
    DECLARE v_prof_his  INT DEFAULT 0;
    DECLARE v_prof_geo  INT DEFAULT 0;

    -- Loops
    DECLARE v_i          INT DEFAULT 0;
    DECLARE v_aluno_id   INT DEFAULT 0;
    DECLARE v_prova_id   INT DEFAULT 0;
    DECLARE v_questao_id INT DEFAULT 0;
    DECLARE v_alt_correta_id INT DEFAULT 0;
    DECLARE v_grade_id   INT DEFAULT 0;
    DECLARE v_diario_id  INT DEFAULT 0;
    DECLARE v_regra_id   INT DEFAULT 0;
    DECLARE v_comp_prova_id INT DEFAULT 0;
    DECLARE v_comp_trab_id  INT DEFAULT 0;
    DECLARE v_evento_id  INT DEFAULT 0;
    DECLARE v_plano_id   INT DEFAULT 0;

    -- ── 1. Ano Letivo ────────────────────────────────────────
    INSERT IGNORE INTO ano_letivo (ano, data_inicio, data_fim, ativo)
        VALUES (2026, '2026-02-01', '2026-12-15', 1);
    SELECT id INTO v_ano_letivo_id FROM ano_letivo WHERE ano = 2026 LIMIT 1;

    -- ── 2. Curso ─────────────────────────────────────────────
    INSERT IGNORE INTO curso (nome, descricao, ativo, ordem)
        VALUES ('Ensino Médio', 'Ensino Médio Regular', 1, 1);
    SELECT id INTO v_curso_id FROM curso WHERE nome = 'Ensino Médio' LIMIT 1;

    -- ── 3. Séries ────────────────────────────────────────────
    INSERT IGNORE INTO serie (nome, curso_id, ordem, ativo) VALUES ('1º Ano EM', v_curso_id, 1, 1);
    INSERT IGNORE INTO serie (nome, curso_id, ordem, ativo) VALUES ('2º Ano EM', v_curso_id, 2, 1);
    INSERT IGNORE INTO serie (nome, curso_id, ordem, ativo) VALUES ('3º Ano EM', v_curso_id, 3, 1);
    SELECT id INTO v_serie1_id FROM serie WHERE nome = '1º Ano EM' AND curso_id = v_curso_id LIMIT 1;
    SELECT id INTO v_serie2_id FROM serie WHERE nome = '2º Ano EM' AND curso_id = v_curso_id LIMIT 1;
    SELECT id INTO v_serie3_id FROM serie WHERE nome = '3º Ano EM' AND curso_id = v_curso_id LIMIT 1;

    -- ── 4. Turmas ────────────────────────────────────────────
    INSERT IGNORE INTO turmas (nome, ano_letivo, serie_id, serie, ativo, tipo_ensino)
        VALUES ('1º Ano A', 2026, v_serie1_id, '1º Ano EM', 1, 'medio');
    INSERT IGNORE INTO turmas (nome, ano_letivo, serie_id, serie, ativo, tipo_ensino)
        VALUES ('2º Ano B', 2026, v_serie2_id, '2º Ano EM', 1, 'medio');
    INSERT IGNORE INTO turmas (nome, ano_letivo, serie_id, serie, ativo, tipo_ensino)
        VALUES ('3º Ano C', 2026, v_serie3_id, '3º Ano EM', 1, 'medio');
    SELECT id INTO v_turma1_id FROM turmas WHERE nome = '1º Ano A' AND ano_letivo = 2026 LIMIT 1;
    SELECT id INTO v_turma2_id FROM turmas WHERE nome = '2º Ano B' AND ano_letivo = 2026 LIMIT 1;
    SELECT id INTO v_turma3_id FROM turmas WHERE nome = '3º Ano C' AND ano_letivo = 2026 LIMIT 1;

    -- ── 5. Matérias ──────────────────────────────────────────
    INSERT IGNORE INTO materias (nome) VALUES ('Português');
    INSERT IGNORE INTO materias (nome) VALUES ('Matemática');
    INSERT IGNORE INTO materias (nome) VALUES ('Física');
    INSERT IGNORE INTO materias (nome) VALUES ('Química');
    INSERT IGNORE INTO materias (nome) VALUES ('Biologia');
    INSERT IGNORE INTO materias (nome) VALUES ('História');
    INSERT IGNORE INTO materias (nome) VALUES ('Geografia');
    SELECT id INTO v_mat_port FROM materias WHERE nome = 'Português' LIMIT 1;
    SELECT id INTO v_mat_mat  FROM materias WHERE nome = 'Matemática' LIMIT 1;
    SELECT id INTO v_mat_fis  FROM materias WHERE nome = 'Física'     LIMIT 1;
    SELECT id INTO v_mat_qui  FROM materias WHERE nome = 'Química'    LIMIT 1;
    SELECT id INTO v_mat_bio  FROM materias WHERE nome = 'Biologia'   LIMIT 1;
    SELECT id INTO v_mat_his  FROM materias WHERE nome = 'História'   LIMIT 1;
    SELECT id INTO v_mat_geo  FROM materias WHERE nome = 'Geografia'  LIMIT 1;

    -- ── 6. Professores ───────────────────────────────────────
    INSERT IGNORE INTO professores (nome, email, senha_hash, password, codigo_prof, materias, turmas, ativo)
        VALUES ('Prof. Ana Português',  'prof.port@demo.educatudo.com', '$2y$10$demohashdemohashdemoha1', 'Demo@2026', 'PORT01',
            JSON_ARRAY(v_mat_port), JSON_ARRAY(v_turma1_id, v_turma2_id, v_turma3_id), 1);
    INSERT IGNORE INTO professores (nome, email, senha_hash, password, codigo_prof, materias, turmas, ativo)
        VALUES ('Prof. Carlos Matemática','prof.mat@demo.educatudo.com', '$2y$10$demohashdemohashdemoha2', 'Demo@2026', 'MAT01',
            JSON_ARRAY(v_mat_mat), JSON_ARRAY(v_turma1_id, v_turma2_id, v_turma3_id), 1);
    INSERT IGNORE INTO professores (nome, email, senha_hash, password, codigo_prof, materias, turmas, ativo)
        VALUES ('Prof. Bruno Física',    'prof.fis@demo.educatudo.com', '$2y$10$demohashdemohashdemoha3', 'Demo@2026', 'FIS01',
            JSON_ARRAY(v_mat_fis), JSON_ARRAY(v_turma1_id, v_turma2_id, v_turma3_id), 1);
    INSERT IGNORE INTO professores (nome, email, senha_hash, password, codigo_prof, materias, turmas, ativo)
        VALUES ('Prof. Carla Química',   'prof.qui@demo.educatudo.com', '$2y$10$demohashdemohashdemoha4', 'Demo@2026', 'QUI01',
            JSON_ARRAY(v_mat_qui), JSON_ARRAY(v_turma1_id, v_turma2_id, v_turma3_id), 1);
    INSERT IGNORE INTO professores (nome, email, senha_hash, password, codigo_prof, materias, turmas, ativo)
        VALUES ('Prof. Diana Biologia',  'prof.bio@demo.educatudo.com', '$2y$10$demohashdemohashdemoha5', 'Demo@2026', 'BIO01',
            JSON_ARRAY(v_mat_bio), JSON_ARRAY(v_turma1_id, v_turma2_id, v_turma3_id), 1);
    INSERT IGNORE INTO professores (nome, email, senha_hash, password, codigo_prof, materias, turmas, ativo)
        VALUES ('Prof. Eduardo História','prof.his@demo.educatudo.com', '$2y$10$demohashdemohashdemoha6', 'Demo@2026', 'HIS01',
            JSON_ARRAY(v_mat_his), JSON_ARRAY(v_turma1_id, v_turma2_id, v_turma3_id), 1);
    INSERT IGNORE INTO professores (nome, email, senha_hash, password, codigo_prof, materias, turmas, ativo)
        VALUES ('Prof. Fátima Geografia','prof.geo@demo.educatudo.com', '$2y$10$demohashdemohashdemoha7', 'Demo@2026', 'GEO01',
            JSON_ARRAY(v_mat_geo), JSON_ARRAY(v_turma1_id, v_turma2_id, v_turma3_id), 1);

    SELECT id INTO v_prof_port FROM professores WHERE email = 'prof.port@demo.educatudo.com' LIMIT 1;
    SELECT id INTO v_prof_mat  FROM professores WHERE email = 'prof.mat@demo.educatudo.com'  LIMIT 1;
    SELECT id INTO v_prof_fis  FROM professores WHERE email = 'prof.fis@demo.educatudo.com'  LIMIT 1;
    SELECT id INTO v_prof_qui  FROM professores WHERE email = 'prof.qui@demo.educatudo.com'  LIMIT 1;
    SELECT id INTO v_prof_bio  FROM professores WHERE email = 'prof.bio@demo.educatudo.com'  LIMIT 1;
    SELECT id INTO v_prof_his  FROM professores WHERE email = 'prof.his@demo.educatudo.com'  LIMIT 1;
    SELECT id INTO v_prof_geo  FROM professores WHERE email = 'prof.geo@demo.educatudo.com'  LIMIT 1;

    -- ── 7. Grade Horária ─────────────────────────────────────
    -- Turma 1 (1º Ano A)
    INSERT IGNORE INTO grade_horaria (turma_id, materia_id, professor_id, dia_semana, horario_de, horario_ate, periodo)
        VALUES (v_turma1_id, v_mat_port, v_prof_port, 1, '07:00', '07:50', 'manha'),
               (v_turma1_id, v_mat_port, v_prof_port, 3, '07:00', '07:50', 'manha'),
               (v_turma1_id, v_mat_mat,  v_prof_mat,  1, '07:50', '08:40', 'manha'),
               (v_turma1_id, v_mat_mat,  v_prof_mat,  3, '07:50', '08:40', 'manha'),
               (v_turma1_id, v_mat_fis,  v_prof_fis,  1, '08:40', '09:30', 'manha'),
               (v_turma1_id, v_mat_fis,  v_prof_fis,  4, '07:00', '07:50', 'manha'),
               (v_turma1_id, v_mat_qui,  v_prof_qui,  2, '07:00', '07:50', 'manha'),
               (v_turma1_id, v_mat_qui,  v_prof_qui,  4, '07:50', '08:40', 'manha'),
               (v_turma1_id, v_mat_bio,  v_prof_bio,  2, '07:50', '08:40', 'manha'),
               (v_turma1_id, v_mat_bio,  v_prof_bio,  5, '07:00', '07:50', 'manha'),
               (v_turma1_id, v_mat_his,  v_prof_his,  2, '08:40', '09:30', 'manha'),
               (v_turma1_id, v_mat_his,  v_prof_his,  5, '07:50', '08:40', 'manha'),
               (v_turma1_id, v_mat_geo,  v_prof_geo,  3, '08:40', '09:30', 'manha'),
               (v_turma1_id, v_mat_geo,  v_prof_geo,  5, '08:40', '09:30', 'manha');

    -- Turma 2 (2º Ano B)
    INSERT IGNORE INTO grade_horaria (turma_id, materia_id, professor_id, dia_semana, horario_de, horario_ate, periodo)
        VALUES (v_turma2_id, v_mat_port, v_prof_port, 1, '10:00', '10:50', 'manha'),
               (v_turma2_id, v_mat_port, v_prof_port, 3, '10:00', '10:50', 'manha'),
               (v_turma2_id, v_mat_mat,  v_prof_mat,  1, '10:50', '11:40', 'manha'),
               (v_turma2_id, v_mat_mat,  v_prof_mat,  3, '10:50', '11:40', 'manha'),
               (v_turma2_id, v_mat_fis,  v_prof_fis,  1, '11:40', '12:30', 'manha'),
               (v_turma2_id, v_mat_fis,  v_prof_fis,  4, '10:00', '10:50', 'manha'),
               (v_turma2_id, v_mat_qui,  v_prof_qui,  2, '10:00', '10:50', 'manha'),
               (v_turma2_id, v_mat_qui,  v_prof_qui,  4, '10:50', '11:40', 'manha'),
               (v_turma2_id, v_mat_bio,  v_prof_bio,  2, '10:50', '11:40', 'manha'),
               (v_turma2_id, v_mat_bio,  v_prof_bio,  5, '10:00', '10:50', 'manha'),
               (v_turma2_id, v_mat_his,  v_prof_his,  2, '11:40', '12:30', 'manha'),
               (v_turma2_id, v_mat_his,  v_prof_his,  5, '10:50', '11:40', 'manha'),
               (v_turma2_id, v_mat_geo,  v_prof_geo,  3, '11:40', '12:30', 'manha'),
               (v_turma2_id, v_mat_geo,  v_prof_geo,  5, '11:40', '12:30', 'manha');

    -- Turma 3 (3º Ano C)
    INSERT IGNORE INTO grade_horaria (turma_id, materia_id, professor_id, dia_semana, horario_de, horario_ate, periodo)
        VALUES (v_turma3_id, v_mat_port, v_prof_port, 1, '13:00', '13:50', 'tarde'),
               (v_turma3_id, v_mat_port, v_prof_port, 3, '13:00', '13:50', 'tarde'),
               (v_turma3_id, v_mat_mat,  v_prof_mat,  1, '13:50', '14:40', 'tarde'),
               (v_turma3_id, v_mat_mat,  v_prof_mat,  3, '13:50', '14:40', 'tarde'),
               (v_turma3_id, v_mat_fis,  v_prof_fis,  1, '14:40', '15:30', 'tarde'),
               (v_turma3_id, v_mat_fis,  v_prof_fis,  4, '13:00', '13:50', 'tarde'),
               (v_turma3_id, v_mat_qui,  v_prof_qui,  2, '13:00', '13:50', 'tarde'),
               (v_turma3_id, v_mat_qui,  v_prof_qui,  4, '13:50', '14:40', 'tarde'),
               (v_turma3_id, v_mat_bio,  v_prof_bio,  2, '13:50', '14:40', 'tarde'),
               (v_turma3_id, v_mat_bio,  v_prof_bio,  5, '13:00', '13:50', 'tarde'),
               (v_turma3_id, v_mat_his,  v_prof_his,  2, '14:40', '15:30', 'tarde'),
               (v_turma3_id, v_mat_his,  v_prof_his,  5, '13:50', '14:40', 'tarde'),
               (v_turma3_id, v_mat_geo,  v_prof_geo,  3, '14:40', '15:30', 'tarde'),
               (v_turma3_id, v_mat_geo,  v_prof_geo,  5, '14:40', '15:30', 'tarde');

    -- ── 8. Alunos (30 por turma) ─────────────────────────────
    SET v_i = 1;
    WHILE v_i <= 30 DO
        -- Turma 1 — 1º Ano A
        INSERT IGNORE INTO alunos (nome, email, senha_hash, password, nickname, ra, turma_id, serie, ativo, status, primeiro_acesso)
        VALUES (
            CONCAT(ELT(v_i, 'Ana','Bruno','Carla','Diego','Eduarda','Felipe','Gabriela','Henrique',
                       'Isabela','João','Kamila','Leonardo','Mariana','Nicolas','Olivia',
                       'Pedro','Quésia','Rafael','Sara','Thiago','Úrsula','Victor','Wanda',
                       'Xavier','Yasmin','Zeca','Alice','Bernardo','Cíntia','Danilo'), ' 1A'),
            CONCAT('aluno1a', v_i, '@demo.educatudo.com'),
            '$2y$10$demohashdemohashdemoha1',
            'Demo@2026',
            CONCAT('aluno1a', v_i),
            LPAD(CONCAT('1', LPAD(v_i, 3, '0')), 8, '0'),
            v_turma1_id, '1º Ano EM', 1, 'ativo', 0
        );

        -- Turma 2 — 2º Ano B
        INSERT IGNORE INTO alunos (nome, email, senha_hash, password, nickname, ra, turma_id, serie, ativo, status, primeiro_acesso)
        VALUES (
            CONCAT(ELT(v_i, 'Ana','Bruno','Carla','Diego','Eduarda','Felipe','Gabriela','Henrique',
                       'Isabela','João','Kamila','Leonardo','Mariana','Nicolas','Olivia',
                       'Pedro','Quésia','Rafael','Sara','Thiago','Úrsula','Victor','Wanda',
                       'Xavier','Yasmin','Zeca','Alice','Bernardo','Cíntia','Danilo'), ' 2B'),
            CONCAT('aluno2b', v_i, '@demo.educatudo.com'),
            '$2y$10$demohashdemohashdemoha1',
            'Demo@2026',
            CONCAT('aluno2b', v_i),
            LPAD(CONCAT('2', LPAD(v_i, 3, '0')), 8, '0'),
            v_turma2_id, '2º Ano EM', 1, 'ativo', 0
        );

        -- Turma 3 — 3º Ano C
        INSERT IGNORE INTO alunos (nome, email, senha_hash, password, nickname, ra, turma_id, serie, ativo, status, primeiro_acesso)
        VALUES (
            CONCAT(ELT(v_i, 'Ana','Bruno','Carla','Diego','Eduarda','Felipe','Gabriela','Henrique',
                       'Isabela','João','Kamila','Leonardo','Mariana','Nicolas','Olivia',
                       'Pedro','Quésia','Rafael','Sara','Thiago','Úrsula','Victor','Wanda',
                       'Xavier','Yasmin','Zeca','Alice','Bernardo','Cíntia','Danilo'), ' 3C'),
            CONCAT('aluno3c', v_i, '@demo.educatudo.com'),
            '$2y$10$demohashdemohashdemoha1',
            'Demo@2026',
            CONCAT('aluno3c', v_i),
            LPAD(CONCAT('3', LPAD(v_i, 3, '0')), 8, '0'),
            v_turma3_id, '3º Ano EM', 1, 'ativo', 0
        );

        SET v_i = v_i + 1;
    END WHILE;

    -- ── 9. Provas (1 por bimestre × 2 matérias × 3 turmas) ──
    -- Helper interno: cria prova, 10 questões, 5 alternativas cada
    -- e realizações + respostas para todos os alunos da turma

    -- Bimestre 1 — Matemática — Turma 1
    CALL seed_demo_prova(v_turma1_id, v_prof_mat, v_mat_mat,
        'Prova de Matemática — 1º Bimestre — 1º Ano A',
        '2026-02-01 08:00:00', '2026-04-30 23:59:00');

    -- Bimestre 1 — Português — Turma 1
    CALL seed_demo_prova(v_turma1_id, v_prof_port, v_mat_port,
        'Prova de Português — 1º Bimestre — 1º Ano A',
        '2026-02-01 08:00:00', '2026-04-30 23:59:00');

    -- Bimestre 2 — Matemática — Turma 1
    CALL seed_demo_prova(v_turma1_id, v_prof_mat, v_mat_mat,
        'Prova de Matemática — 2º Bimestre — 1º Ano A',
        '2026-05-01 08:00:00', '2026-07-15 23:59:00');

    -- Bimestre 2 — Português — Turma 1
    CALL seed_demo_prova(v_turma1_id, v_prof_port, v_mat_port,
        'Prova de Português — 2º Bimestre — 1º Ano A',
        '2026-05-01 08:00:00', '2026-07-15 23:59:00');

    -- Bimestre 3 — Matemática — Turma 1
    CALL seed_demo_prova(v_turma1_id, v_prof_mat, v_mat_mat,
        'Prova de Matemática — 3º Bimestre — 1º Ano A',
        '2026-08-01 08:00:00', '2026-10-15 23:59:00');

    -- Bimestre 3 — Português — Turma 1
    CALL seed_demo_prova(v_turma1_id, v_prof_port, v_mat_port,
        'Prova de Português — 3º Bimestre — 1º Ano A',
        '2026-08-01 08:00:00', '2026-10-15 23:59:00');

    -- Bimestre 4 — Matemática — Turma 1
    CALL seed_demo_prova(v_turma1_id, v_prof_mat, v_mat_mat,
        'Prova de Matemática — 4º Bimestre — 1º Ano A',
        '2026-10-16 08:00:00', '2026-12-15 23:59:00');

    -- Bimestre 4 — Português — Turma 1
    CALL seed_demo_prova(v_turma1_id, v_prof_port, v_mat_port,
        'Prova de Português — 4º Bimestre — 1º Ano A',
        '2026-10-16 08:00:00', '2026-12-15 23:59:00');

    -- Turma 2 — 2º Ano B
    CALL seed_demo_prova(v_turma2_id, v_prof_mat, v_mat_mat, 'Prova de Matemática — 1º Bimestre — 2º Ano B', '2026-02-01 08:00:00', '2026-04-30 23:59:00');
    CALL seed_demo_prova(v_turma2_id, v_prof_port, v_mat_port, 'Prova de Português — 1º Bimestre — 2º Ano B', '2026-02-01 08:00:00', '2026-04-30 23:59:00');
    CALL seed_demo_prova(v_turma2_id, v_prof_mat, v_mat_mat, 'Prova de Matemática — 2º Bimestre — 2º Ano B', '2026-05-01 08:00:00', '2026-07-15 23:59:00');
    CALL seed_demo_prova(v_turma2_id, v_prof_port, v_mat_port, 'Prova de Português — 2º Bimestre — 2º Ano B', '2026-05-01 08:00:00', '2026-07-15 23:59:00');
    CALL seed_demo_prova(v_turma2_id, v_prof_mat, v_mat_mat, 'Prova de Matemática — 3º Bimestre — 2º Ano B', '2026-08-01 08:00:00', '2026-10-15 23:59:00');
    CALL seed_demo_prova(v_turma2_id, v_prof_port, v_mat_port, 'Prova de Português — 3º Bimestre — 2º Ano B', '2026-08-01 08:00:00', '2026-10-15 23:59:00');
    CALL seed_demo_prova(v_turma2_id, v_prof_mat, v_mat_mat, 'Prova de Matemática — 4º Bimestre — 2º Ano B', '2026-10-16 08:00:00', '2026-12-15 23:59:00');
    CALL seed_demo_prova(v_turma2_id, v_prof_port, v_mat_port, 'Prova de Português — 4º Bimestre — 2º Ano B', '2026-10-16 08:00:00', '2026-12-15 23:59:00');

    -- Turma 3 — 3º Ano C
    CALL seed_demo_prova(v_turma3_id, v_prof_mat, v_mat_mat, 'Prova de Matemática — 1º Bimestre — 3º Ano C', '2026-02-01 08:00:00', '2026-04-30 23:59:00');
    CALL seed_demo_prova(v_turma3_id, v_prof_port, v_mat_port, 'Prova de Português — 1º Bimestre — 3º Ano C', '2026-02-01 08:00:00', '2026-04-30 23:59:00');
    CALL seed_demo_prova(v_turma3_id, v_prof_mat, v_mat_mat, 'Prova de Matemática — 2º Bimestre — 3º Ano C', '2026-05-01 08:00:00', '2026-07-15 23:59:00');
    CALL seed_demo_prova(v_turma3_id, v_prof_port, v_mat_port, 'Prova de Português — 2º Bimestre — 3º Ano C', '2026-05-01 08:00:00', '2026-07-15 23:59:00');
    CALL seed_demo_prova(v_turma3_id, v_prof_mat, v_mat_mat, 'Prova de Matemática — 3º Bimestre — 3º Ano C', '2026-08-01 08:00:00', '2026-10-15 23:59:00');
    CALL seed_demo_prova(v_turma3_id, v_prof_port, v_mat_port, 'Prova de Português — 3º Bimestre — 3º Ano C', '2026-08-01 08:00:00', '2026-10-15 23:59:00');
    CALL seed_demo_prova(v_turma3_id, v_prof_mat, v_mat_mat, 'Prova de Matemática — 4º Bimestre — 3º Ano C', '2026-10-16 08:00:00', '2026-12-15 23:59:00');
    CALL seed_demo_prova(v_turma3_id, v_prof_port, v_mat_port, 'Prova de Português — 4º Bimestre — 3º Ano C', '2026-10-16 08:00:00', '2026-12-15 23:59:00');

    -- ── 10. Planos de Aula ───────────────────────────────────
    INSERT IGNORE INTO planos_aula (professor_id, materia_id, turma_id, data_aula, titulo, conteudo, metodologia, status) VALUES
    (v_prof_port, v_mat_port, v_turma1_id, '["2026-02-10"]', 'Interpretação de Texto', 'Análise de textos dissertativos e argumentativos.', 'Leitura coletiva e debate em grupo.', 'aprovado'),
    (v_prof_port, v_mat_port, v_turma1_id, '["2026-05-05"]', 'Gramática e Sintaxe',    'Análise sintática e período composto.', 'Exercícios práticos e correção coletiva.', 'aprovado'),
    (v_prof_port, v_mat_port, v_turma1_id, '["2026-08-10"]', 'Literatura Brasileira',  'Modernismo — 1ª e 2ª fase.', 'Análise de obras e contextualização histórica.', 'aprovado'),
    (v_prof_port, v_mat_port, v_turma1_id, '["2026-10-20"]', 'Redação Dissertativa',   'Estrutura da redação do ENEM.', 'Produção textual orientada e correção individual.', 'aprovado'),
    (v_prof_mat,  v_mat_mat,  v_turma1_id, '["2026-02-12"]', 'Funções do 2º Grau',     'Raízes, vértice e gráfico da parábola.', 'Resolução de exercícios no quadro.', 'aprovado'),
    (v_prof_mat,  v_mat_mat,  v_turma1_id, '["2026-05-07"]', 'Geometria Analítica',    'Ponto, reta e circunferência no plano cartesiano.', 'Exercícios e uso de GeoGebra.', 'aprovado'),
    (v_prof_mat,  v_mat_mat,  v_turma1_id, '["2026-08-12"]', 'Trigonometria',          'Seno, cosseno e tangente — aplicações.', 'Demonstrações e lista de exercícios.', 'aprovado'),
    (v_prof_mat,  v_mat_mat,  v_turma1_id, '["2026-10-22"]', 'Progressões',            'PA e PG — fórmulas e aplicações.', 'Exercícios contextualizados.', 'aprovado'),
    (v_prof_fis,  v_mat_fis,  v_turma1_id, '["2026-02-14"]', 'Cinemática',             'MRU e MRUV — gráficos e equações.', 'Aula expositiva com experimentos.', 'aprovado'),
    (v_prof_qui,  v_mat_qui,  v_turma1_id, '["2026-02-16"]', 'Ligações Químicas',      'Iônica, covalente e metálica.', 'Modelos moleculares e exercícios.', 'aprovado'),
    (v_prof_bio,  v_mat_bio,  v_turma1_id, '["2026-02-18"]', 'Citologia',              'Estrutura da célula eucariota e procariota.', 'Slides e análise de imagens microscópicas.', 'aprovado'),
    (v_prof_his,  v_mat_his,  v_turma1_id, '["2026-02-20"]', 'Revolução Industrial',   'Causas, desenvolvimento e impactos sociais.', 'Linha do tempo e análise de fontes.', 'aprovado'),
    (v_prof_geo,  v_mat_geo,  v_turma1_id, '["2026-02-22"]', 'Geopolítica Mundial',    'Blocos econômicos e relações de poder.', 'Análise de mapas e debates.', 'aprovado'),
    -- Turma 2
    (v_prof_port, v_mat_port, v_turma2_id, '["2026-02-10"]', 'Literatura Portuguesa', 'Trovadorismo e Humanismo.', 'Leitura de textos e análise contextual.', 'aprovado'),
    (v_prof_mat,  v_mat_mat,  v_turma2_id, '["2026-02-12"]', 'Probabilidade',         'Espaço amostral e eventos.', 'Exercícios práticos e jogos.', 'aprovado'),
    (v_prof_fis,  v_mat_fis,  v_turma2_id, '["2026-02-14"]', 'Termodinâmica',         'Leis da termodinâmica e máquinas térmicas.', 'Aula expositiva e resolução de problemas.', 'aprovado'),
    -- Turma 3
    (v_prof_port, v_mat_port, v_turma3_id, '["2026-02-10"]', 'Pós-Modernismo',        'Características e obras representativas.', 'Análise textual e debate.', 'aprovado'),
    (v_prof_mat,  v_mat_mat,  v_turma3_id, '["2026-02-12"]', 'Análise Combinatória',  'Permutação, combinação e arranjo.', 'Exercícios com situações reais.', 'aprovado'),
    (v_prof_fis,  v_mat_fis,  v_turma3_id, '["2026-02-14"]', 'Óptica Geométrica',     'Reflexão, refração e lentes.', 'Experimentos simples e cálculos.', 'aprovado');

    -- ── 11. Diário de Aulas ──────────────────────────────────
    -- Turma 1: 4 aulas por matéria em fev/mar
    SELECT id INTO v_grade_id FROM grade_horaria WHERE turma_id = v_turma1_id AND materia_id = v_mat_port AND dia_semana = 1 LIMIT 1;
    SELECT id INTO v_plano_id FROM planos_aula WHERE turma_id = v_turma1_id AND materia_id = v_mat_port LIMIT 1;
    INSERT IGNORE INTO diario_aulas (grade_horaria_id, professor_id, turma_id, materia_id, plano_aula_id, data_aula, execucao, conteudo_realizado, status) VALUES
    (v_grade_id, v_prof_port, v_turma1_id, v_mat_port, v_plano_id, '2026-02-09', 'conforme_planejado', 'Interpretação de texto dissertativo.', 'registrado'),
    (v_grade_id, v_prof_port, v_turma1_id, v_mat_port, v_plano_id, '2026-02-16', 'conforme_planejado', 'Coesão e coerência textual.', 'registrado'),
    (v_grade_id, v_prof_port, v_turma1_id, v_mat_port, v_plano_id, '2026-02-23', 'conforme_planejado', 'Análise de textos argumentativos.', 'registrado'),
    (v_grade_id, v_prof_port, v_turma1_id, v_mat_port, v_plano_id, '2026-03-02', 'conforme_planejado', 'Produção de texto dissertativo.', 'registrado');

    SELECT id INTO v_grade_id FROM grade_horaria WHERE turma_id = v_turma1_id AND materia_id = v_mat_mat AND dia_semana = 1 LIMIT 1;
    SELECT id INTO v_plano_id FROM planos_aula WHERE turma_id = v_turma1_id AND materia_id = v_mat_mat LIMIT 1;
    INSERT IGNORE INTO diario_aulas (grade_horaria_id, professor_id, turma_id, materia_id, plano_aula_id, data_aula, execucao, conteudo_realizado, status) VALUES
    (v_grade_id, v_prof_mat, v_turma1_id, v_mat_mat, v_plano_id, '2026-02-09', 'conforme_planejado', 'Funções do 2º grau — conceitos.', 'registrado'),
    (v_grade_id, v_prof_mat, v_turma1_id, v_mat_mat, v_plano_id, '2026-02-16', 'conforme_planejado', 'Equação do 2º grau — Bhaskara.', 'registrado'),
    (v_grade_id, v_prof_mat, v_turma1_id, v_mat_mat, v_plano_id, '2026-02-23', 'conforme_planejado', 'Gráfico da parábola.', 'registrado'),
    (v_grade_id, v_prof_mat, v_turma1_id, v_mat_mat, v_plano_id, '2026-03-02', 'conforme_planejado', 'Exercícios de fixação.', 'registrado');

    -- ── 12. Frequências para as aulas registradas ────────────
    CALL seed_demo_frequencias(v_turma1_id, v_mat_port, v_prof_port);
    CALL seed_demo_frequencias(v_turma1_id, v_mat_mat, v_prof_mat);
    CALL seed_demo_frequencias(v_turma2_id, v_mat_port, v_prof_port);
    CALL seed_demo_frequencias(v_turma3_id, v_mat_port, v_prof_port);

    -- ── 13. Faltas por bimestre ──────────────────────────────
    INSERT IGNORE INTO faltas_eventos (nome, bimestre, ano_letivo, turmas_json, materias_json, created_by, ativo)
    VALUES
    ('1º Bimestre — Controle de Faltas', 1, 2026,
        JSON_ARRAY(v_turma1_id, v_turma2_id, v_turma3_id),
        JSON_ARRAY(v_mat_port, v_mat_mat, v_mat_fis, v_mat_qui, v_mat_bio, v_mat_his, v_mat_geo), 1, 1),
    ('2º Bimestre — Controle de Faltas', 2, 2026,
        JSON_ARRAY(v_turma1_id, v_turma2_id, v_turma3_id),
        JSON_ARRAY(v_mat_port, v_mat_mat, v_mat_fis, v_mat_qui, v_mat_bio, v_mat_his, v_mat_geo), 1, 1),
    ('3º Bimestre — Controle de Faltas', 3, 2026,
        JSON_ARRAY(v_turma1_id, v_turma2_id, v_turma3_id),
        JSON_ARRAY(v_mat_port, v_mat_mat, v_mat_fis, v_mat_qui, v_mat_bio, v_mat_his, v_mat_geo), 1, 1),
    ('4º Bimestre — Controle de Faltas', 4, 2026,
        JSON_ARRAY(v_turma1_id, v_turma2_id, v_turma3_id),
        JSON_ARRAY(v_mat_port, v_mat_mat, v_mat_fis, v_mat_qui, v_mat_bio, v_mat_his, v_mat_geo), 1, 1);

    -- Lança faltas para primeiros 6 alunos de cada turma (bimestre 1)
    SELECT id INTO v_evento_id FROM faltas_eventos WHERE bimestre = 1 AND ano_letivo = 2026 AND nome LIKE '1º Bimestre%' LIMIT 1;
    CALL seed_demo_faltas(v_evento_id, v_turma1_id, v_mat_mat, 6);
    CALL seed_demo_faltas(v_evento_id, v_turma1_id, v_mat_port, 4);
    CALL seed_demo_faltas(v_evento_id, v_turma2_id, v_mat_mat, 5);
    CALL seed_demo_faltas(v_evento_id, v_turma3_id, v_mat_fis, 7);

    SELECT id INTO v_evento_id FROM faltas_eventos WHERE bimestre = 2 AND ano_letivo = 2026 AND nome LIKE '2º Bimestre%' LIMIT 1;
    CALL seed_demo_faltas(v_evento_id, v_turma1_id, v_mat_bio, 3);
    CALL seed_demo_faltas(v_evento_id, v_turma2_id, v_mat_qui, 5);

    -- ── 14. Boletim ──────────────────────────────────────────
    -- 1 regra por bimestre por turma (cobre todas as matérias)
    INSERT IGNORE INTO boletim_regras (nome, codigo, ativo, formula_final, materias_ids, series_ids, turmas_ids, ano_letivo, bimestre, nota_minima_aprovacao, usar_resultado_aprovacao)
    VALUES
    ('Boletim 1º Bim — 1º Ano A', 'EM_T1_B1_2026', 1, '(PROVA * 0.7) + (TRABALHO * 0.3)',
        JSON_ARRAY(v_mat_port,v_mat_mat,v_mat_fis,v_mat_qui,v_mat_bio,v_mat_his,v_mat_geo),
        JSON_ARRAY(v_serie1_id), JSON_ARRAY(v_turma1_id), 2026, 1, 6.0, 1),
    ('Boletim 2º Bim — 1º Ano A', 'EM_T1_B2_2026', 1, '(PROVA * 0.7) + (TRABALHO * 0.3)',
        JSON_ARRAY(v_mat_port,v_mat_mat,v_mat_fis,v_mat_qui,v_mat_bio,v_mat_his,v_mat_geo),
        JSON_ARRAY(v_serie1_id), JSON_ARRAY(v_turma1_id), 2026, 2, 6.0, 1),
    ('Boletim 3º Bim — 1º Ano A', 'EM_T1_B3_2026', 1, '(PROVA * 0.7) + (TRABALHO * 0.3)',
        JSON_ARRAY(v_mat_port,v_mat_mat,v_mat_fis,v_mat_qui,v_mat_bio,v_mat_his,v_mat_geo),
        JSON_ARRAY(v_serie1_id), JSON_ARRAY(v_turma1_id), 2026, 3, 6.0, 1),
    ('Boletim 4º Bim — 1º Ano A', 'EM_T1_B4_2026', 1, '(PROVA * 0.7) + (TRABALHO * 0.3)',
        JSON_ARRAY(v_mat_port,v_mat_mat,v_mat_fis,v_mat_qui,v_mat_bio,v_mat_his,v_mat_geo),
        JSON_ARRAY(v_serie1_id), JSON_ARRAY(v_turma1_id), 2026, 4, 6.0, 1),
    ('Boletim 1º Bim — 2º Ano B', 'EM_T2_B1_2026', 1, '(PROVA * 0.7) + (TRABALHO * 0.3)',
        JSON_ARRAY(v_mat_port,v_mat_mat,v_mat_fis,v_mat_qui,v_mat_bio,v_mat_his,v_mat_geo),
        JSON_ARRAY(v_serie2_id), JSON_ARRAY(v_turma2_id), 2026, 1, 6.0, 1),
    ('Boletim 2º Bim — 2º Ano B', 'EM_T2_B2_2026', 1, '(PROVA * 0.7) + (TRABALHO * 0.3)',
        JSON_ARRAY(v_mat_port,v_mat_mat,v_mat_fis,v_mat_qui,v_mat_bio,v_mat_his,v_mat_geo),
        JSON_ARRAY(v_serie2_id), JSON_ARRAY(v_turma2_id), 2026, 2, 6.0, 1),
    ('Boletim 1º Bim — 3º Ano C', 'EM_T3_B1_2026', 1, '(PROVA * 0.7) + (TRABALHO * 0.3)',
        JSON_ARRAY(v_mat_port,v_mat_mat,v_mat_fis,v_mat_qui,v_mat_bio,v_mat_his,v_mat_geo),
        JSON_ARRAY(v_serie3_id), JSON_ARRAY(v_turma3_id), 2026, 1, 6.0, 1),
    ('Boletim 2º Bim — 3º Ano C', 'EM_T3_B2_2026', 1, '(PROVA * 0.7) + (TRABALHO * 0.3)',
        JSON_ARRAY(v_mat_port,v_mat_mat,v_mat_fis,v_mat_qui,v_mat_bio,v_mat_his,v_mat_geo),
        JSON_ARRAY(v_serie3_id), JSON_ARRAY(v_turma3_id), 2026, 2, 6.0, 1);

    -- Componentes para cada regra
    CALL seed_demo_componentes_boletim('EM_T1_B1_2026');
    CALL seed_demo_componentes_boletim('EM_T1_B2_2026');
    CALL seed_demo_componentes_boletim('EM_T1_B3_2026');
    CALL seed_demo_componentes_boletim('EM_T1_B4_2026');
    CALL seed_demo_componentes_boletim('EM_T2_B1_2026');
    CALL seed_demo_componentes_boletim('EM_T2_B2_2026');
    CALL seed_demo_componentes_boletim('EM_T3_B1_2026');
    CALL seed_demo_componentes_boletim('EM_T3_B2_2026');

    -- Notas manuais (trabalho) para alunos da turma 1, bimestre 1
    CALL seed_demo_notas_manuais('EM_T1_B1_2026', v_turma1_id, 'B1_2026');
    CALL seed_demo_notas_manuais('EM_T1_B2_2026', v_turma1_id, 'B2_2026');
    CALL seed_demo_notas_manuais('EM_T2_B1_2026', v_turma2_id, 'B1_2026');
    CALL seed_demo_notas_manuais('EM_T3_B1_2026', v_turma3_id, 'B1_2026');

END$$

-- ── Procedure auxiliar: cria prova + questões + alternativas + realizações ──
CREATE PROCEDURE seed_demo_prova(
    IN p_turma_id  INT,
    IN p_prof_id   INT,
    IN p_mat_id    INT,
    IN p_titulo    VARCHAR(255),
    IN p_inicio    DATETIME,
    IN p_fim       DATETIME
)
BEGIN
    DECLARE v_prova_id    INT DEFAULT 0;
    DECLARE v_questao_id  INT DEFAULT 0;
    DECLARE v_alt3_id     INT DEFAULT 0;
    DECLARE v_aluno_id    INT DEFAULT 0;
    DECLARE v_acertos     INT DEFAULT 0;
    DECLARE v_nota        DECIMAL(8,2) DEFAULT 0;
    DECLARE v_q           INT DEFAULT 0;
    DECLARE v_done        INT DEFAULT 0;

    DECLARE cur_alunos CURSOR FOR
        SELECT id FROM alunos WHERE turma_id = p_turma_id AND ativo = 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    -- Cria prova (ignora se já existir pelo título)
    INSERT IGNORE INTO provas (titulo, professor_id, materia_id, turma_id,
        data_inicio, data_fim, valor_total, liberada, status)
    VALUES (p_titulo, p_prof_id, p_mat_id, p_turma_id,
        p_inicio, p_fim, 10, 1, 'encerrada');

    SELECT id INTO v_prova_id FROM provas WHERE titulo = p_titulo AND turma_id = p_turma_id LIMIT 1;

    -- 10 questões de múltipla escolha
    SET v_q = 1;
    WHILE v_q <= 10 DO
        INSERT IGNORE INTO provas_questoes (prova_id, enunciado, tipo, valor, ordem)
        VALUES (v_prova_id,
            CONCAT('Questão ', v_q, ' — ', p_titulo, ': assinale a alternativa correta.'),
            'multipla_escolha', 1.0, v_q);

        SELECT id INTO v_questao_id FROM provas_questoes
            WHERE prova_id = v_prova_id AND ordem = v_q LIMIT 1;

        -- 5 alternativas; a 3ª é a correta
        INSERT IGNORE INTO provas_alternativas (questao_id, texto, correta, ordem)
        VALUES
            (v_questao_id, CONCAT('Alternativa A — questão ', v_q), 0, 1),
            (v_questao_id, CONCAT('Alternativa B — questão ', v_q), 0, 2),
            (v_questao_id, CONCAT('Alternativa C — questão ', v_q, ' (correta)'), 1, 3),
            (v_questao_id, CONCAT('Alternativa D — questão ', v_q), 0, 4),
            (v_questao_id, CONCAT('Alternativa E — questão ', v_q), 0, 5);

        SET v_q = v_q + 1;
    END WHILE;

    -- Realizações + respostas para cada aluno
    OPEN cur_alunos;
    lp: LOOP
        FETCH cur_alunos INTO v_aluno_id;
        IF v_done THEN LEAVE lp; END IF;

        -- Acertos: 6 a 10 (distribuição simples via MOD)
        SET v_acertos = 6 + (v_aluno_id MOD 5);
        SET v_nota    = v_acertos * 1.0;

        INSERT IGNORE INTO provas_realizacoes (prova_id, aluno_id, iniciado_em, finalizado_em, nota, status)
        VALUES (v_prova_id, v_aluno_id,
            DATE_SUB(p_fim, INTERVAL 2 HOUR),
            DATE_SUB(p_fim, INTERVAL 1 HOUR),
            v_nota, 'finalizado');

        -- Respostas
        SET v_q = 1;
        WHILE v_q <= 10 DO
            SELECT id INTO v_questao_id FROM provas_questoes
                WHERE prova_id = v_prova_id AND ordem = v_q LIMIT 1;

            -- Se dentro dos acertos, responde a correta (opção 3); senão opção 1
            IF v_q <= v_acertos THEN
                SELECT id INTO v_alt3_id FROM provas_alternativas
                    WHERE questao_id = v_questao_id AND correta = 1 LIMIT 1;
                INSERT IGNORE INTO provas_respostas (prova_id, aluno_id, questao_id, alternativa_id, correta, pontuacao)
                VALUES (v_prova_id, v_aluno_id, v_questao_id, v_alt3_id, 1, 1.0);
            ELSE
                SELECT id INTO v_alt3_id FROM provas_alternativas
                    WHERE questao_id = v_questao_id AND ordem = 1 LIMIT 1;
                INSERT IGNORE INTO provas_respostas (prova_id, aluno_id, questao_id, alternativa_id, correta, pontuacao)
                VALUES (v_prova_id, v_aluno_id, v_questao_id, v_alt3_id, 0, 0.0);
            END IF;

            SET v_q = v_q + 1;
        END WHILE;
    END LOOP;
    CLOSE cur_alunos;
    SET v_done = 0;
END$$

-- ── Procedure auxiliar: frequências ─────────────────────────────────────────
CREATE PROCEDURE seed_demo_frequencias(
    IN p_turma_id INT,
    IN p_mat_id   INT,
    IN p_prof_id  INT
)
BEGIN
    DECLARE v_diario_id INT DEFAULT 0;
    DECLARE v_aluno_id  INT DEFAULT 0;
    DECLARE v_sit       VARCHAR(30);
    DECLARE v_done      INT DEFAULT 0;

    DECLARE cur_diarios CURSOR FOR
        SELECT id FROM diario_aulas WHERE turma_id = p_turma_id AND materia_id = p_mat_id;
    DECLARE cur_alunos CURSOR FOR
        SELECT id FROM alunos WHERE turma_id = p_turma_id AND ativo = 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur_diarios;
    d_loop: LOOP
        FETCH cur_diarios INTO v_diario_id;
        IF v_done THEN LEAVE d_loop; END IF;

        SET v_done = 0;
        OPEN cur_alunos;
        a_loop: LOOP
            FETCH cur_alunos INTO v_aluno_id;
            IF v_done THEN LEAVE a_loop; END IF;

            SET v_sit = IF(v_aluno_id MOD 10 = 0, 'falta',
                        IF(v_aluno_id MOD 7 = 0, 'atraso', 'presente'));

            INSERT IGNORE INTO diario_frequencias (diario_aula_id, aluno_id, situacao)
            VALUES (v_diario_id, v_aluno_id, v_sit);
        END LOOP;
        CLOSE cur_alunos;
        SET v_done = 0;
    END LOOP;
    CLOSE cur_diarios;
END$$

-- ── Procedure auxiliar: faltas ───────────────────────────────────────────────
CREATE PROCEDURE seed_demo_faltas(
    IN p_evento_id INT,
    IN p_turma_id  INT,
    IN p_mat_id    INT,
    IN p_qtd_alunos INT
)
BEGIN
    DECLARE v_aluno_id INT;
    DECLARE v_count    INT DEFAULT 0;
    DECLARE v_done     INT DEFAULT 0;

    DECLARE cur CURSOR FOR
        SELECT id FROM alunos WHERE turma_id = p_turma_id AND ativo = 1 LIMIT 30;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    OPEN cur;
    lp: LOOP
        FETCH cur INTO v_aluno_id;
        IF v_done OR v_count >= p_qtd_alunos THEN LEAVE lp; END IF;

        INSERT IGNORE INTO faltas_lancamentos (evento_id, aluno_id, materia_id, faltas, observacao)
        VALUES (p_evento_id, v_aluno_id, p_mat_id,
            2 + (v_aluno_id MOD 7), NULL);

        SET v_count = v_count + 1;
    END LOOP;
    CLOSE cur;
END$$

-- ── Procedure auxiliar: componentes de boletim ───────────────────────────────
CREATE PROCEDURE seed_demo_componentes_boletim(IN p_codigo VARCHAR(60))
BEGIN
    DECLARE v_regra_id INT DEFAULT 0;
    SELECT id INTO v_regra_id FROM boletim_regras WHERE codigo = p_codigo LIMIT 1;
    IF v_regra_id > 0 THEN
        INSERT IGNORE INTO boletim_componentes
            (regra_id, codigo, nome, source_type, calc_type, peso, materia_unica, escala_max, obrigatorio, ativo, ordem)
        VALUES
            (v_regra_id, 'PROVA',    'Prova Bimestral',   'provas_sistema', 'media', 0.7, 0, 10.00, 1, 1, 1),
            (v_regra_id, 'TRABALHO', 'Trabalho/Atividade','manual',         'media', 0.3, 0, 10.00, 0, 1, 2);
    END IF;
END$$

-- ── Procedure auxiliar: notas manuais (trabalho) ─────────────────────────────
CREATE PROCEDURE seed_demo_notas_manuais(
    IN p_regra_codigo VARCHAR(60),
    IN p_turma_id     INT,
    IN p_periodo_ref  VARCHAR(20)
)
BEGIN
    DECLARE v_regra_id     INT DEFAULT 0;
    DECLARE v_comp_id      INT DEFAULT 0;
    DECLARE v_aluno_id     INT DEFAULT 0;
    DECLARE v_nota         DECIMAL(8,2);
    DECLARE v_done         INT DEFAULT 0;

    DECLARE cur CURSOR FOR
        SELECT id FROM alunos WHERE turma_id = p_turma_id AND ativo = 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;

    SELECT id INTO v_regra_id FROM boletim_regras WHERE codigo = p_regra_codigo LIMIT 1;
    SELECT id INTO v_comp_id  FROM boletim_componentes WHERE regra_id = v_regra_id AND codigo = 'TRABALHO' LIMIT 1;

    IF v_regra_id > 0 AND v_comp_id > 0 THEN
        OPEN cur;
        lp: LOOP
            FETCH cur INTO v_aluno_id;
            IF v_done THEN LEAVE lp; END IF;

            -- Nota entre 6.0 e 10.0 usando MOD
            SET v_nota = 6.0 + ((v_aluno_id MOD 40) / 10.0);

            INSERT IGNORE INTO boletim_notas_manuais
                (regra_id, componente_id, aluno_id, periodo_ref, nota)
            VALUES (v_regra_id, v_comp_id, v_aluno_id, p_periodo_ref, v_nota);
        END LOOP;
        CLOSE cur;
    END IF;
END$$

DELIMITER ;

-- ── Executa e remove procedures ──────────────────────────────────────────────
CALL seed_demo_ensino_medio();

DROP PROCEDURE IF EXISTS seed_demo_ensino_medio;
DROP PROCEDURE IF EXISTS seed_demo_prova;
DROP PROCEDURE IF EXISTS seed_demo_frequencias;
DROP PROCEDURE IF EXISTS seed_demo_faltas;
DROP PROCEDURE IF EXISTS seed_demo_componentes_boletim;
DROP PROCEDURE IF EXISTS seed_demo_notas_manuais;
