-- =============================================================================
-- Apaga somente o seed Santa Maria Ana 2024 (prefixo SM24).
-- Não cria demonstrativo/boletim e não mexe em outros seeds.
--
-- Master → Migrations → escola → Escolher → marque SÓ este arquivo.
-- =============================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS apagar_santa_maria_ana_2024;

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
    DELETE FROM recuperacoes WHERE nome IN ('Recuperação semestral', 'Prova Final')
      AND (ano_letivo = 2024 OR regra_id IN (SELECT id FROM regras_academicas WHERE codigo = 'sm24-aprovacao-2024'));
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

DELIMITER ;

CALL apagar_santa_maria_ana_2024();
DROP PROCEDURE IF EXISTS apagar_santa_maria_ana_2024;