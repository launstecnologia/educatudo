-- Continua de onde parou: a turma "Carga 5000" já existe.
-- O UPDATE dos alunos tinha falhado no modo seguro do Workbench (1175).
-- Rode este arquivo INTEIRO em educa_002_colag.

USE educa_002_colag;

SET SQL_SAFE_UPDATES = 0;

SET @turma_id := (SELECT id FROM turmas WHERE nome = 'Carga 5000' ORDER BY id DESC LIMIT 1);
SET @ano_letivo_id := COALESCE(
    (SELECT ano_letivo_id FROM turmas WHERE id = @turma_id),
    (SELECT id FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC LIMIT 1)
);
SET @professor_id := (SELECT id FROM professores WHERE ativo = 1 ORDER BY id ASC LIMIT 1);
SET @admin_id := (SELECT id FROM usuarios WHERE tipo = 'admin_escola' ORDER BY id ASC LIMIT 1);

SELECT @turma_id AS turma_id, @professor_id AS professor_id, @admin_id AS admin_id;

-- Matricular os 5 mil na turma
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

-- Tipos
INSERT IGNORE INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem)
VALUES
    ('Prova Bimestral', 'Avaliação principal do bimestre.', 1, 20),
    ('Trabalho', 'Trabalho / atividade do bimestre.', 1, 25);

SET @tipo_prova := (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'Prova Bimestral' ORDER BY id LIMIT 1);
SET @tipo_trab  := (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'Trabalho' ORDER BY id LIMIT 1);

-- Eventos
INSERT INTO provas_blocos (
    titulo, descricao, data_prova, hora_inicio, hora_fim,
    criado_por, professor_id, tipo_prova, formato_evento, configuracao_nota,
    liberar_gabarito, turma_id, ativo, liberado, status, gabarito_liberado,
    ano_letivo, bimestre, tipo_avaliacao_id, visivel_no_portal_aluno
)
SELECT
    'Carga — Prova Bimestral 1º Bim',
    'Lançamento de nota da prova bimestral para alunos de carga.',
    '2026-04-15', '07:00:00', '23:59:59',
    @admin_id, @professor_id, 'original', 'lancamento_nota', 'coordenacao_calcula',
    'imediatamente', @turma_id, 1, 1, 'liberado', 1,
    2026, 1, @tipo_prova, 1
FROM DUAL
WHERE @turma_id IS NOT NULL AND @professor_id IS NOT NULL AND @admin_id IS NOT NULL
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
    'Lançamento de nota do trabalho para alunos de carga.',
    '2026-04-20', '07:00:00', '23:59:59',
    @admin_id, @professor_id, 'original', 'lancamento_nota', 'coordenacao_calcula',
    'imediatamente', @turma_id, 1, 1, 'liberado', 1,
    2026, 1, @tipo_trab, 1
FROM DUAL
WHERE @turma_id IS NOT NULL AND @professor_id IS NOT NULL AND @admin_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM provas_blocos
       WHERE titulo = 'Carga — Trabalho 1º Bim' AND deleted_at IS NULL
  );

SET @bloco_prova := (
    SELECT id FROM provas_blocos
     WHERE titulo = 'Carga — Prova Bimestral 1º Bim' AND deleted_at IS NULL LIMIT 1
);
SET @bloco_trab := (
    SELECT id FROM provas_blocos
     WHERE titulo = 'Carga — Trabalho 1º Bim' AND deleted_at IS NULL LIMIT 1
);

INSERT IGNORE INTO provas_blocos_turmas (bloco_id, turma_id)
VALUES (@bloco_prova, @turma_id), (@bloco_trab, @turma_id);

INSERT IGNORE INTO provas_blocos_professores (bloco_id, professor_id, materia_id, quantidade_questoes)
SELECT @bloco_prova, @professor_id, m.id, 0
  FROM (SELECT id FROM materias ORDER BY id LIMIT 5) m;

INSERT IGNORE INTO provas_blocos_professores (bloco_id, professor_id, materia_id, quantidade_questoes)
SELECT @bloco_trab, @professor_id, m.id, 0
  FROM (SELECT id FROM materias ORDER BY id LIMIT 5) m;

INSERT IGNORE INTO provas_blocos_professores_turmas (bloco_professor_id, turma_id)
SELECT pbp.id, @turma_id
  FROM provas_blocos_professores pbp
 WHERE pbp.bloco_id IN (@bloco_prova, @bloco_trab);

-- Notas
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
    @bloco_prova AS bloco_prova_id,
    @bloco_trab AS bloco_trabalho_id,
    (SELECT COUNT(*) FROM alunos WHERE turma_id = @turma_id AND nickname REGEXP '^carga[0-9]{5}$') AS alunos_na_turma,
    (SELECT COUNT(*) FROM provas_blocos_notas_lancadas WHERE bloco_id = @bloco_prova) AS notas_prova,
    (SELECT COUNT(*) FROM provas_blocos_notas_lancadas WHERE bloco_id = @bloco_trab) AS notas_trabalho;
