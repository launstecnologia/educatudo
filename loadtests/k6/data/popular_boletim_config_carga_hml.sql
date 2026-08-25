-- EducaTudo — só configura o evento de BOLETIM (não gera nada).
-- Depois: Admin → Notas e Boletim → "Carga — 1º Bimestre" → Gerar boletins.
--
-- Depende de já existirem:
--   turma "Carga 5000"
--   evento "Carga — Prova Bimestral 1º Bim"
--   evento "Carga — Trabalho 1º Bim"
--   notas lançadas nesses eventos
--
-- Média do boletim: (PROVA * 0.7) + (TRABALHO * 0.3)  |  aprovação >= 6,0

USE educa_002_colag;

SET SQL_SAFE_UPDATES = 0;

SET @turma_id := (SELECT id FROM turmas WHERE nome = 'Carga 5000' ORDER BY id DESC LIMIT 1);
SET @bloco_prova := (
    SELECT id FROM provas_blocos
     WHERE titulo = 'Carga — Prova Bimestral 1º Bim' AND deleted_at IS NULL LIMIT 1
);
SET @bloco_trab := (
    SELECT id FROM provas_blocos
     WHERE titulo = 'Carga — Trabalho 1º Bim' AND deleted_at IS NULL LIMIT 1
);

SELECT
    @turma_id AS turma_id,
    @bloco_prova AS bloco_prova_id,
    @bloco_trab AS bloco_trabalho_id,
    (SELECT COUNT(*) FROM alunos WHERE turma_id = @turma_id AND nickname REGEXP '^carga[0-9]{5}$') AS alunos_na_turma,
    (SELECT COUNT(*) FROM provas_blocos_notas_lancadas WHERE bloco_id = @bloco_prova) AS notas_prova,
    (SELECT COUNT(*) FROM provas_blocos_notas_lancadas WHERE bloco_id = @bloco_trab) AS notas_trabalho;
-- Se turma_id, bloco_prova_id ou bloco_trabalho_id for NULL, pare. O lançamento ainda não rodou.

SET @materias_json := (
    SELECT JSON_ARRAYAGG(materia_id)
      FROM (
          SELECT DISTINCT materia_id
            FROM provas_blocos_professores
           WHERE bloco_id IN (@bloco_prova, @bloco_trab)
      ) x
);

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
    'Boletim de carga: 70% prova bimestral + 30% trabalho. Só configurar — gerar pelo botão no admin.',
    '(PROVA * 0.7) + (TRABALHO * 0.3)',
    @materias_json,
    JSON_ARRAY(),
    JSON_ARRAY(@turma_id),
    'boletim',
    2026, 1, 6.00, 1,
    1, 1, 1, 'none', 2,
    '2026-02-01', '2026-04-30', 1
FROM DUAL
WHERE @turma_id IS NOT NULL
  AND @bloco_prova IS NOT NULL
  AND @bloco_trab IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM boletim_regras WHERE codigo = 'CARGA_B1_2026');

UPDATE boletim_regras
   SET nome = 'Carga — 1º Bimestre',
       formula_final = '(PROVA * 0.7) + (TRABALHO * 0.3)',
       materias_ids = @materias_json,
       turmas_ids = JSON_ARRAY(@turma_id),
       series_ids = JSON_ARRAY(),
       exibir_em = 'boletim',
       ano_letivo = 2026,
       bimestre = 1,
       nota_minima_aprovacao = 6.00,
       usar_resultado_aprovacao = 1,
       vis_aluno = 1,
       vis_pais = 1,
       vis_coordenacao = 1,
       default_data_inicio = '2026-02-01',
       default_data_fim = '2026-04-30',
       ativo = 1
 WHERE codigo = 'CARGA_B1_2026';

SET @regra_id := (SELECT id FROM boletim_regras WHERE codigo = 'CARGA_B1_2026' LIMIT 1);

DELETE c
  FROM boletim_componentes c
 WHERE c.regra_id = @regra_id;

-- Colunas do boletim
INSERT INTO boletim_componentes
    (regra_id, codigo, nome, source_type, calc_type, peso, bloco_id, blocos_ids,
     config_json, materia_unica, usar_percentual, escala_max, obrigatorio, ativo, ordem)
VALUES
    (@regra_id, 'PROVA', 'Prova Bimestral', 'provas_sistema', 'media', 0.700,
     @bloco_prova, CAST(@bloco_prova AS CHAR),
     '{"layout":{"group":"b1","type":"media"}}', 0, 1, 10.00, 1, 1, 1),
    (@regra_id, 'TRABALHO', 'Trabalho', 'provas_sistema', 'media', 0.300,
     @bloco_trab, CAST(@bloco_trab AS CHAR),
     '{"layout":{"group":"b1","type":"other"}}', 0, 1, 10.00, 0, 1, 2),
    (@regra_id, 'MEDIA', 'Média', 'calculado', 'media', 1.000,
     NULL, NULL,
     '{"expressao":"(PROVA * 0.7) + (TRABALHO * 0.3)","formula_mode":"single","layout":{"group":"final","type":"media"}}',
     0, 0, 10.00, 1, 1, 3);

SELECT
    r.id AS regra_id,
    r.nome,
    r.codigo,
    r.formula_final,
    r.turmas_ids,
    r.nota_minima_aprovacao,
    (SELECT COUNT(*) FROM boletim_componentes c WHERE c.regra_id = r.id) AS colunas
FROM boletim_regras r
WHERE r.codigo = 'CARGA_B1_2026';
