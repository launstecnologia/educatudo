-- =============================================================================
-- Rollback da carga 2026_11_16_importar_dados_santa_maria_ana_2024_frequencia.sql
-- Remove só frequência, ocorrências e faltas desta aluna. Não apaga o boletim.
-- Não aparece no Escolher do Master (sufixo _rollback). Para a tela, use o arquivo _apagar.sql.
-- =============================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS apagar_sm24_frequencia_ana;

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


DELIMITER ;

CALL apagar_sm24_frequencia_ana();
DROP PROCEDURE IF EXISTS apagar_sm24_frequencia_ana;
