-- EducaTudo — PASSO 1: criar eventos e lançar notas nos 5 mil alunos de carga
-- NÃO gera boletim ainda.
--
-- Rode o script INTEIRO de uma vez, no banco do tenant da HML
-- (o nome_banco ligado a hml-escola.educatudo.com).
--
-- Cria:
--   turma "Carga 5000"
--   evento "Carga — Prova Bimestral 1º Bim"  (lançamento de nota)
--   evento "Carga — Trabalho 1º Bim"         (lançamento de nota)
--   uma nota por aluno × matéria em cada evento

-- Workbench: sem isso o UPDATE dos 5 mil estoura Error 1175 (safe update).
SET SQL_SAFE_UPDATES = 0;

-- 0) Tem que ser 5000. Se for 0, você está no banco errado.
SELECT COUNT(*) AS alunos_carga
FROM alunos
WHERE nickname REGEXP '^carga[0-9]{5}$';

-- 1) Turma
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
SET @ano_letivo_id := COALESCE(
    (SELECT ano_letivo_id FROM turmas WHERE id = @turma_id),
    (SELECT id FROM ano_letivo WHERE ativo = 1 ORDER BY ano DESC LIMIT 1)
);
SET @professor_id := (SELECT id FROM professores WHERE ativo = 1 ORDER BY id ASC LIMIT 1);
SET @admin_id := (SELECT id FROM usuarios WHERE tipo = 'admin_escola' ORDER BY id ASC LIMIT 1);

SELECT @turma_id AS turma_id, @professor_id AS professor_id, @admin_id AS admin_id;
-- Se algum for NULL, pare. Precisa de turma, professor e admin no banco.

-- 2) Alunos de carga na turma (WHERE pelo id — chave primária)
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

-- 3) Tipos
INSERT IGNORE INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem)
VALUES
    ('Prova Bimestral', 'Avaliação principal do bimestre.', 1, 20),
    ('Trabalho', 'Trabalho / atividade do bimestre.', 1, 25);

SET @tipo_prova := (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'Prova Bimestral' ORDER BY id LIMIT 1);
SET @tipo_trab  := (SELECT id FROM provas_tipos_avaliacao WHERE nome = 'Trabalho' ORDER BY id LIMIT 1);

-- 4) Eventos (formato lançamento de nota, sem questões)
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

-- 5) Lançar nota em todos os carga (prova e trabalho × até 5 matérias)
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

-- Conferência: prova e trabalho devem ficar perto de 25000 cada (5000 alunos × 5 matérias)
SELECT
    @bloco_prova AS bloco_prova_id,
    @bloco_trab AS bloco_trabalho_id,
    (SELECT COUNT(*) FROM alunos WHERE turma_id = @turma_id AND nickname REGEXP '^carga[0-9]{5}$') AS alunos_na_turma,
    (SELECT COUNT(*) FROM provas_blocos_notas_lancadas WHERE bloco_id = @bloco_prova) AS notas_prova,
    (SELECT COUNT(*) FROM provas_blocos_notas_lancadas WHERE bloco_id = @bloco_trab) AS notas_trabalho;
