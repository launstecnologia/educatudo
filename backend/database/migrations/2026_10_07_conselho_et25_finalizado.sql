-- Conselho de Classe finalizado da população ET25 (Ensino Médio 2025).
-- Sessões dos 3 trimestres já fechadas, com deliberação de cada aluno.
-- Quem ficou entre 5 e 6 recebe a decisão aprovado_conselho.
-- Idempotente: não duplica sessão nem lançamento já gravado.
-- Tenant. Rollback: 2026_10_07_conselho_et25_finalizado_rollback.sql
-- Em escola sem o seed ET25, os INSERTs não geram linha.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @admin := COALESCE(
  (SELECT id FROM usuarios WHERE email = 'admin@educateste.local' LIMIT 1),
  (SELECT id FROM usuarios WHERE tipo = 'admin_escola' ORDER BY id LIMIT 1)
);

INSERT INTO conselho_sessoes (
  turma_id, ano_letivo, bimestre, status, data_reuniao, pauta,
  criado_por, aberto_por, aberto_em, finalizado_por, finalizado_em
)
SELECT t.id, 2025, per.bim, 'finalizado', per.data_reuniao,
       CONCAT('ET25 Conselho ', per.rotulo, ' — ', t.nome),
       @admin, @admin, TIMESTAMP(per.data_reuniao, '18:00:00'),
       @admin, TIMESTAMP(per.data_reuniao, '19:30:00')
FROM turmas t
CROSS JOIN (
  SELECT 1 AS bim, '2025-05-16' AS data_reuniao, '1º trimestre' AS rotulo
  UNION ALL SELECT 2, '2025-08-29', '2º trimestre'
  UNION ALL SELECT 3, '2025-12-12', 'final'
) per
WHERE t.observacoes LIKE 'ET25 %'
  AND NOT EXISTS (
    SELECT 1 FROM conselho_sessoes cs
    WHERE cs.turma_id = t.id AND cs.ano_letivo = 2025 AND cs.bimestre = per.bim
  );

INSERT INTO conselho_participantes (sessao_id, usuario_id, nome, cargo, presente)
SELECT cs.id, @admin, 'Helena Duarte', 'coordenacao', 1
FROM conselho_sessoes cs
INNER JOIN turmas t ON t.id = cs.turma_id
WHERE t.observacoes LIKE 'ET25 %' AND cs.pauta LIKE 'ET25 Conselho %'
  AND NOT EXISTS (
    SELECT 1 FROM conselho_participantes p
    WHERE p.sessao_id = cs.id AND p.cargo = 'coordenacao'
  );

INSERT INTO conselho_participantes (sessao_id, professor_id, nome, cargo, presente)
SELECT cs.id, p.id, p.nome, 'professor', 1
FROM conselho_sessoes cs
INNER JOIN turmas t ON t.id = cs.turma_id AND t.observacoes LIKE 'ET25 %'
INNER JOIN (
  SELECT DISTINCT turma_id, professor_id FROM grade_horaria
) g ON g.turma_id = t.id
INNER JOIN professores p ON p.id = g.professor_id
WHERE cs.pauta LIKE 'ET25 Conselho %'
  AND NOT EXISTS (
    SELECT 1 FROM conselho_participantes px
    WHERE px.sessao_id = cs.id AND px.professor_id = p.id
  );

INSERT INTO conselho_deliberacoes (
  sessao_id, aluno_id, materia_id, resultado_anterior, resultado_decisao, justificativa, registrado_por
)
SELECT cs.id, r.aluno_id, NULL,
  CASE r.situacao
    WHEN 'aprovado_conselho' THEN 'reprovado_rendimento'
    WHEN 'aprovado_recuperacao' THEN 'recuperacao'
    ELSE r.situacao
  END,
  CASE r.situacao
    WHEN 'aprovado_conselho' THEN 'aprovado_conselho'
    WHEN 'aprovado' THEN 'manter'
    WHEN 'aprovado_recuperacao' THEN 'manter'
    WHEN 'reprovado_rendimento' THEN 'retido'
    WHEN 'reprovado_frequencia' THEN 'manter'
    WHEN 'transferido' THEN 'transferido'
    ELSE 'manter'
  END,
  CASE r.situacao
    WHEN 'aprovado_conselho' THEN 'ET25 Média entre 5 e 6 após recuperação. Conselho delibera aprovação pela trajetória e pelo comprometimento.'
    WHEN 'aprovado_recuperacao' THEN 'ET25 Aprovado após recuperação. Conselho mantém o resultado.'
    WHEN 'reprovado_rendimento' THEN 'ET25 Médias abaixo da mínima. Conselho registra a retenção.'
    WHEN 'reprovado_frequencia' THEN 'ET25 Frequência abaixo do mínimo. Conselho mantém a reprovação por frequência.'
    WHEN 'transferido' THEN 'ET25 Aluno transferido. Conselho registra a movimentação.'
    WHEN 'desistente' THEN 'ET25 Desistência registrada. Conselho mantém a situação.'
    ELSE 'ET25 Conselho mantém o resultado preliminar do período.'
  END,
  @admin
FROM conselho_sessoes cs
INNER JOIN turmas t ON t.id = cs.turma_id AND t.observacoes LIKE 'ET25 %'
INNER JOIN resultado_academico r
  ON r.turma_id = t.id AND r.ano_letivo = 2025 AND r.status = 'homologado'
 AND (
      (cs.bimestre IN (1, 2) AND r.periodo_tipo = 'trimestre' AND r.periodo_numero = cs.bimestre)
   OR (cs.bimestre = 3 AND r.periodo_tipo = 'ano' AND r.periodo_numero = 0)
 )
WHERE cs.pauta LIKE 'ET25 Conselho %'
  AND NOT EXISTS (
    SELECT 1 FROM conselho_deliberacoes d0
    WHERE d0.sessao_id = cs.id AND d0.aluno_id = r.aluno_id AND d0.materia_id IS NULL
  );

INSERT INTO conselho_encaminhamentos (sessao_id, aluno_id, tipo, detalhe, criado_por)
SELECT d.sessao_id, d.aluno_id,
  CASE
    WHEN d.resultado_decisao = 'aprovado_conselho' THEN 'decisao_final'
    WHEN d.resultado_decisao = 'retido' THEN 'recuperacao'
    ELSE 'contato_responsavel'
  END,
  d.justificativa,
  @admin
FROM conselho_deliberacoes d
INNER JOIN conselho_sessoes cs ON cs.id = d.sessao_id AND cs.pauta LIKE 'ET25 Conselho %'
WHERE (d.resultado_decisao IN ('aprovado_conselho', 'retido') OR d.resultado_anterior = 'reprovado_frequencia')
  AND NOT EXISTS (
    SELECT 1 FROM conselho_encaminhamentos e
    WHERE e.sessao_id = d.sessao_id AND e.aluno_id = d.aluno_id AND e.detalhe = d.justificativa
  );

INSERT INTO conselho_observacoes (sessao_id, aluno_id, professor_id, texto)
SELECT cs.id, d.aluno_id, prof.professor_id,
       'ET25 Participou da recuperação e apresentou evolução. Indico aprovação pelo Conselho.'
FROM conselho_deliberacoes d
INNER JOIN conselho_sessoes cs ON cs.id = d.sessao_id AND cs.pauta LIKE 'ET25 Conselho %'
INNER JOIN (
  SELECT turma_id, MIN(professor_id) AS professor_id
  FROM grade_horaria
  GROUP BY turma_id
) prof ON prof.turma_id = cs.turma_id
WHERE d.resultado_decisao = 'aprovado_conselho'
  AND NOT EXISTS (
    SELECT 1 FROM conselho_observacoes o
    WHERE o.sessao_id = cs.id AND o.aluno_id = d.aluno_id AND o.professor_id = prof.professor_id
  );

INSERT INTO conselho_atas (sessao_id, pauta, sintese, decisoes, conteudo_json, gerada_por, gerada_em)
SELECT cs.id, cs.pauta,
       'ET25 Reunião finalizada. Rendimento, frequência e recuperação conferidos com a equipe.',
       'ET25 Deliberações lançadas. Casos entre 5 e 6 ficam aprovados pelo Conselho; retenção, frequência e transferência permanecem registradas.',
       JSON_OBJECT('origem', 'ET25', 'status', 'finalizado'),
       @admin, cs.finalizado_em
FROM conselho_sessoes cs
WHERE cs.pauta LIKE 'ET25 Conselho %'
  AND NOT EXISTS (SELECT 1 FROM conselho_atas a WHERE a.sessao_id = cs.id);
