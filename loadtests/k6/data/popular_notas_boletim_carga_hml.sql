-- EducaTudo — carga HML: turma + prova bimestral + trabalho + notas + evento de boletim
-- Rode no MESMO banco que o site usa (o da linha dominio = hml-escola.educatudo.com no master).
--
-- Depois, no admin: /admin/boletim-configuracao
--   Evento "Carga — 1º Bimestre" → Gerar boletins
-- Isso monta até 5000 boletins de uma vez (teste de performance).
--
-- Idempotente: pode repetir.

SET SQL_SAFE_UPDATES = 0;

-- ============================================================================
-- 0) Conferência — se alunos_carga = 0, este NÃO é o banco do site
-- ============================================================================
SELECT COUNT(*) AS alunos_carga
FROM alunos
WHERE nickname REGEXP '^carga[0-9]{5}$';

-- ============================================================================
-- 1) Turma só para os 5 mil (não mistura com turma letiva real)
-- ============================================================================
INSERT INTO turmas (nome, ano_letivo, ano_letivo_id, serie, serie_id, curso_id, ativo, tipo_ensino)
SELECT
    'Carga 5000',
    t.ano_letivo,
    t.ano_letivo_id,
    t.serie,
    t.serie_id,
    t.curso_id,
    1,
    t.tipo_ensino
FROM turmas t
WHERE t.ativo = 1
  AND NOT EXISTS (SELECT 1 FROM turmas x WHERE x.nome = 'Carga 5000')
ORDER BY t.id
LIMIT 1;

SET @turma_id := (SELECT id FROM turmas WHERE nome = 'Carga 5000' ORDER BY id DESC LIMIT 1);
SET @ano_letivo_id := (
    SELECT COALESCE(
        (SELECT ano_letivo_id FROM turmas WHERE id = @turma_id),
        (SELECT id FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC LIMIT 1)
    )
);
SET @professor_id := (SELECT id FROM professores WHERE ativo = 1 ORDER BY id ASC LIMIT 1);
SET @admin_id := (
    SELECT id FROM usuarios WHERE tipo = 'admin_escola' ORDER BY id ASC LIMIT 1
);

SELECT
    @turma_id AS turma_id,
    @ano_letivo_id AS ano_letivo_id,
    @professor_id AS professor_id,
    @admin_id AS admin_id;

-- Se algum desses vier NULL, NÃO continue. Precisa existir turma, professor e admin.

-- ============================================================================
-- 2) Matricular todos os carga na turma
-- ============================================================================
UPDATE alunos a
INNER JOIN turmas t ON t.id = @turma_id
INNER JOIN (
    SELECT id FROM alunos WHERE nickname REGEXP '^carga[0-9]{5}$'
) c ON c.id = a.id
   SET a.turma_id = @turma_id,
       a.serie = t.serie,
       a.ativo = 1;

INSERT IGNORE INTO matricula
    (aluno_id, turma_id, ano_letivo_id, data_entrada, status, created_at, updated_at)
SELECT a.id, @turma_id, @ano_letivo_id, CURDATE(), 'ativa', NOW(), NOW()
  FROM alunos a
 WHERE a.nickname REGEXP '^carga[0-9]{5}$'
   AND @ano_letivo_id IS NOT NULL;

SELECT COUNT(*) AS alunos_na_turma
FROM alunos
WHERE turma_id = @turma_id
  AND nickname REGEXP '^carga[0-9]{5}$';

-- ============================================================================
-- 3) Tipos de avaliação
-- ============================================================================
INSERT IGNORE INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem)
VALUES
    ('Prova Bimestral', 'Avaliação principal do bimestre.', 1, 20),
    ('Trabalho', 'Trabalho / atividade do bimestre.', 1, 25);

SET @tipo_prova := (
    SELECT id FROM provas_tipos_avaliacao
     WHERE deleted_at IS NULL AND nome = 'Prova Bimestral'
     ORDER BY id LIMIT 1
);
SET @tipo_trab := (
    SELECT id FROM provas_tipos_avaliacao
     WHERE deleted_at IS NULL AND nome = 'Trabalho'
     ORDER BY id LIMIT 1
);

-- ============================================================================
-- 4) Eventos de lançamento (sem questões)
-- ============================================================================
INSERT INTO provas_blocos (
    titulo, descricao, data_prova, hora_inicio, hora_fim,
    criado_por, professor_id, tipo_prova, formato_evento, configuracao_nota,
    liberar_gabarito, turma_id, ativo, liberado, status, gabarito_liberado,
    ano_letivo, bimestre, tipo_avaliacao_id, visivel_no_portal_aluno
)
SELECT
    'Carga — Prova Bimestral 1º Bim',
    'Evento de carga: notas lançadas para 5000 alunos.',
    '2026-04-15', '07:00:00', '23:59:59',
    @admin_id, @professor_id, 'original', 'lancamento_nota', 'coordenacao_calcula',
    'imediatamente', @turma_id, 1, 1, 'liberado', 1,
    2026, 1, @tipo_prova, 1
FROM DUAL
WHERE @turma_id IS NOT NULL
  AND @professor_id IS NOT NULL
  AND @admin_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM provas_blocos
       WHERE titulo = 'Carga — Prova Bimestral 1º Bim' AND deleted_at IS NULL
  );

INSERT INTO provas_blocos (
    titulo, descricao, data_prova, hora_inicio, hora_fim,
    criado_por, professor_id, tipo_prova, formato_evento, configuracao_nota,
    liberar_gabarito, turma_id, ativo, liberado, status, gabarito_liberado,
    ano_letivo, bimestre, tipo_avaliacao_id, visivel_no_portal_aluno
)
SELECT
    'Carga — Trabalho 1º Bim',
    'Evento de carga: trabalho lançado para 5000 alunos.',
    '2026-04-20', '07:00:00', '23:59:59',
    @admin_id, @professor_id, 'original', 'lancamento_nota', 'coordenacao_calcula',
    'imediatamente', @turma_id, 1, 1, 'liberado', 1,
    2026, 1, @tipo_trab, 1
FROM DUAL
WHERE @turma_id IS NOT NULL
  AND @professor_id IS NOT NULL
  AND @admin_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM provas_blocos
       WHERE titulo = 'Carga — Trabalho 1º Bim' AND deleted_at IS NULL
  );

SET @bloco_prova := (
    SELECT id FROM provas_blocos
     WHERE titulo = 'Carga — Prova Bimestral 1º Bim' AND deleted_at IS NULL
     LIMIT 1
);
SET @bloco_trab := (
    SELECT id FROM provas_blocos
     WHERE titulo = 'Carga — Trabalho 1º Bim' AND deleted_at IS NULL
     LIMIT 1
);

INSERT IGNORE INTO provas_blocos_turmas (bloco_id, turma_id)
VALUES (@bloco_prova, @turma_id), (@bloco_trab, @turma_id);

-- Até 5 matérias da escola
INSERT IGNORE INTO provas_blocos_professores (bloco_id, professor_id, materia_id, quantidade_questoes)
SELECT @bloco_prova, @professor_id, m.id, 0
  FROM materias m
 ORDER BY m.id
 LIMIT 5;

INSERT IGNORE INTO provas_blocos_professores (bloco_id, professor_id, materia_id, quantidade_questoes)
SELECT @bloco_trab, @professor_id, m.id, 0
  FROM materias m
 ORDER BY m.id
 LIMIT 5;

-- ============================================================================
-- 5) Notas: 5.0 a 9.9 por aluno/matéria (prova um pouco maior que trabalho)
-- ============================================================================
INSERT IGNORE INTO provas_blocos_notas_lancadas
    (bloco_id, professor_id, materia_id, turma_id, aluno_id, nota, observacao)
SELECT
    @bloco_prova,
    @professor_id,
    pbp.materia_id,
    @turma_id,
    a.id,
    LEAST(10, ROUND(6.0 + ((a.id + pbp.materia_id) % 40) / 10, 1)),
    'Carga HML — prova bimestral'
FROM alunos a
INNER JOIN provas_blocos_professores pbp ON pbp.bloco_id = @bloco_prova
WHERE a.nickname REGEXP '^carga[0-9]{5}$';

INSERT IGNORE INTO provas_blocos_notas_lancadas
    (bloco_id, professor_id, materia_id, turma_id, aluno_id, nota, observacao)
SELECT
    @bloco_trab,
    @professor_id,
    pbp.materia_id,
    @turma_id,
    a.id,
    LEAST(10, ROUND(5.5 + ((a.id * 3 + pbp.materia_id) % 45) / 10, 1)),
    'Carga HML — trabalho'
FROM alunos a
INNER JOIN provas_blocos_professores pbp ON pbp.bloco_id = @bloco_trab
WHERE a.nickname REGEXP '^carga[0-9]{5}$';

SELECT
    (SELECT COUNT(*) FROM provas_blocos_notas_lancadas WHERE bloco_id = @bloco_prova) AS notas_prova,
    (SELECT COUNT(*) FROM provas_blocos_notas_lancadas WHERE bloco_id = @bloco_trab) AS notas_trabalho,
    @bloco_prova AS bloco_prova_id,
    @bloco_trab AS bloco_trabalho_id;

-- ============================================================================
-- 6) Evento de boletim apontando para esses dois blocos + só a turma Carga 5000
-- ============================================================================
INSERT INTO boletim_regras (
    nome, codigo, descricao_curta, formula_final,
    materias_ids, series_ids, turmas_ids, exibir_em,
    ano_letivo, bimestre, nota_minima_aprovacao, usar_resultado_aprovacao,
    vis_aluno, vis_pais, vis_coordenacao, round_mode, decimal_places,
    default_data_inicio, default_data_fim, ativo
)
SELECT
    'Carga — 1º Bimestre',
    'CARGA_B1_2026',
    'Evento de carga para gerar 5000 boletins de uma vez.',
    '(PROVA * 0.7) + (TRABALHO * 0.3)',
    (SELECT JSON_ARRAYAGG(materia_id) FROM (
         SELECT materia_id FROM provas_blocos_professores WHERE bloco_id = @bloco_prova LIMIT 5
     ) x),
    JSON_ARRAY(),
    JSON_ARRAY(@turma_id),
    'boletim',
    2026, 1, 6.00, 1,
    1, 1, 1, 'none', 2,
    '2026-02-01', '2026-04-30', 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM boletim_regras WHERE codigo = 'CARGA_B1_2026');

UPDATE boletim_regras
   SET turmas_ids = JSON_ARRAY(@turma_id),
       ativo = 1,
       vis_aluno = 1,
       vis_coordenacao = 1
 WHERE codigo = 'CARGA_B1_2026';

SET @regra_id := (SELECT id FROM boletim_regras WHERE codigo = 'CARGA_B1_2026' LIMIT 1);

INSERT INTO boletim_componentes
    (regra_id, codigo, nome, source_type, calc_type, peso, bloco_id, blocos_ids,
     materia_unica, usar_percentual, escala_max, obrigatorio, ativo, ordem)
SELECT @regra_id, 'PROVA', 'Prova Bimestral', 'provas_sistema', 'media', 0.700,
       @bloco_prova, CAST(@bloco_prova AS CHAR), 0, 1, 10.00, 1, 1, 1
FROM DUAL
WHERE @regra_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM boletim_componentes
       WHERE regra_id = @regra_id AND codigo = 'PROVA'
  );

INSERT INTO boletim_componentes
    (regra_id, codigo, nome, source_type, calc_type, peso, bloco_id, blocos_ids,
     materia_unica, usar_percentual, escala_max, obrigatorio, ativo, ordem)
SELECT @regra_id, 'TRABALHO', 'Trabalho', 'provas_sistema', 'media', 0.300,
       @bloco_trab, CAST(@bloco_trab AS CHAR), 0, 1, 10.00, 0, 1, 2
FROM DUAL
WHERE @regra_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM boletim_componentes
       WHERE regra_id = @regra_id AND codigo = 'TRABALHO'
  );

SELECT
    @regra_id AS regra_boletim_id,
    @turma_id AS turma_id,
    'Carga — 1º Bimestre' AS evento,
    'Admin → Boletim → Carga — 1º Bimestre → Gerar boletins' AS proximo_passo;
