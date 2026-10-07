-- =============================================================================
-- Apaga somente o seed Ensino Médio 2025 da Escola Educa Teste (prefixo ET25).
-- Não mexe no seed de 2026 nem em curso, séries, unidade ou usuários.
--
-- Master → Migrations → Escola teste → Escolher → marque SÓ este arquivo.
-- O seed 2026_10_06_importar_dados_escola_teste_em_2025.sql também apaga
-- antes de recriar. Use este arquivo quando quiser só esvaziar.
-- =============================================================================
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

DROP PROCEDURE IF EXISTS apagar_escola_teste_em_2025;

DELIMITER $$

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

  DELETE df FROM diario_fechamentos df
  INNER JOIN turmas t ON t.id = df.turma_id
  WHERE t.observacoes LIKE 'ET25 %';

  DELETE FROM planos_aula WHERE titulo LIKE 'ET25 %';

  DELETE b FROM boletins b
  INNER JOIN boletim_regras r ON r.id = b.regra_id
  WHERE r.codigo LIKE 'et25-%';

  DELETE FROM boletins
  WHERE ano_letivo = 2025 AND (nome LIKE 'Boletim % T% 2025' OR nome = 'Boletim Ensino Médio 2025');

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

  DELETE o FROM conselho_observacoes o
  INNER JOIN conselho_sessoes cs ON cs.id = o.sessao_id
  INNER JOIN turmas t ON t.id = cs.turma_id
  WHERE t.observacoes LIKE 'ET25 %';

  DELETE e FROM conselho_encaminhamentos e
  INNER JOIN conselho_sessoes cs ON cs.id = e.sessao_id
  INNER JOIN turmas t ON t.id = cs.turma_id
  WHERE t.observacoes LIKE 'ET25 %';

  DELETE d FROM conselho_deliberacoes d
  INNER JOIN conselho_sessoes cs ON cs.id = d.sessao_id
  INNER JOIN turmas t ON t.id = cs.turma_id
  WHERE t.observacoes LIKE 'ET25 %';

  DELETE a FROM conselho_atas a
  INNER JOIN conselho_sessoes cs ON cs.id = a.sessao_id
  INNER JOIN turmas t ON t.id = cs.turma_id
  WHERE t.observacoes LIKE 'ET25 %';

  DELETE p FROM conselho_participantes p
  INNER JOIN conselho_sessoes cs ON cs.id = p.sessao_id
  INNER JOIN turmas t ON t.id = cs.turma_id
  WHERE t.observacoes LIKE 'ET25 %';

  DELETE cs FROM conselho_sessoes cs
  INNER JOIN turmas t ON t.id = cs.turma_id
  WHERE t.observacoes LIKE 'ET25 %';

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


DELIMITER ;

CALL apagar_escola_teste_em_2025();
DROP PROCEDURE IF EXISTS apagar_escola_teste_em_2025;
