-- Força o fechamento do ano 2025 como HOMOLOGADO e remove os documentos finais
-- já emitidos (ficha, ata/consolidado, boletim, histórico), para a escola
-- gerar de novo em Consolidação Final / Impressão em lote.
--
-- Escopo: ano_letivo = 2025 (período ano). Idempotente.
-- Tenant. Rollback: 2026_10_09_fechamento_2025_homologado_limpar_docs_rollback.sql
-- Isolamento é pela conexão PDO; sem coluna escola_id.
--
-- DELETE de emissões: intencional — o objetivo é reemitir. Rollback não
-- restaura o conteúdo dos PDFs/snapshots apagados.
--
-- PDFs físicos em storage (fechamento/impressao) não são apagados aqui;
-- rode também o deploy com a versão de chave PDF atualizada no
-- ImpressaoLoteFechamentoService (v6) para a tela não reaproveitar arquivo antigo.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @db := DATABASE();
SET @ano := 2025;

SET @admin := COALESCE(
  (SELECT id FROM usuarios WHERE email = 'admin@educateste.local' LIMIT 1),
  (SELECT id FROM usuarios WHERE email = 'admin@educa.local' LIMIT 1),
  (SELECT id FROM usuarios WHERE tipo = 'admin_escola' ORDER BY id LIMIT 1)
);

SET @has_fech := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'fechamento_periodo'
);
SET @has_turmas := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'turmas'
);
SET @has_res := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'resultado_academico'
);
SET @has_emis := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'resultado_documento_emissoes'
);
SET @has_hist := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'historico_documentos'
);
SET @has_hist_ass := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'historico_assinaturas'
);
SET @has_pdfs := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'aluno_pdfs_emitidos'
);
SET @has_fech_hist := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'fechamento_periodo_historico'
);

-- ── 1. Cria fechamento ano 2025 vigente (HOMOLOGADO) onde faltar ──
SET @sql := IF(@has_fech = 0 OR @has_turmas = 0, 'SELECT 1',
  "INSERT INTO fechamento_periodo (
      turma_id, ano_letivo, periodo_tipo, periodo_numero, periodo_ref, status,
      homologado_em, homologado_por, justificativa, vigente, vigente_chave
   )
   SELECT t.id, 2025, 'ano', 0, '2025-ANO', 'HOMOLOGADO',
          NOW(), @admin,
          'Migration 2026_10_09: homologa 2025 para regenerar documentos finais',
          1, CONCAT(t.id, ':2025:ano:0')
   FROM turmas t
   WHERE t.ano_letivo = 2025
     AND (t.ativo = 1 OR t.ativo IS NULL)
     AND NOT EXISTS (
       SELECT 1 FROM fechamento_periodo f
       WHERE f.turma_id = t.id
         AND f.ano_letivo = 2025
         AND f.periodo_tipo = 'ano'
         AND f.periodo_numero = 0
         AND f.vigente = 1
     )");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. Auditoria (status anterior) e depois força HOMOLOGADO ──
SET @sql := IF(@has_fech = 0 OR @has_fech_hist = 0, 'SELECT 1',
  "INSERT INTO fechamento_periodo_historico
      (fechamento_id, status_anterior, status_novo, justificativa, usuario_id, payload_json)
   SELECT f.id, f.status, 'HOMOLOGADO',
          'Migration 2026_10_09: força HOMOLOGADO 2025', @admin,
          JSON_OBJECT('status_anterior', f.status, 'origem', '2026_10_09')
   FROM fechamento_periodo f
   WHERE f.ano_letivo = 2025
     AND f.periodo_tipo = 'ano'
     AND f.periodo_numero = 0
     AND f.vigente = 1
     AND f.status <> 'HOMOLOGADO'
     AND NOT EXISTS (
       SELECT 1 FROM fechamento_periodo_historico h
       WHERE h.fechamento_id = f.id
         AND h.justificativa LIKE 'Migration 2026_10_09%'
     )");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_fech = 0, 'SELECT 1',
  "UPDATE fechamento_periodo
   SET status = 'HOMOLOGADO',
       homologado_em = COALESCE(homologado_em, NOW()),
       homologado_por = COALESCE(homologado_por, @admin),
       justificativa = CASE
         WHEN status = 'HOMOLOGADO' THEN justificativa
         ELSE CONCAT(
           IFNULL(NULLIF(justificativa, ''), ''),
           IF(IFNULL(justificativa, '') = '', '', ' | '),
           'Migration 2026_10_09: força HOMOLOGADO 2025'
         )
       END,
       updated_at = NOW()
   WHERE ano_letivo = 2025
     AND periodo_tipo = 'ano'
     AND periodo_numero = 0
     AND vigente = 1
     AND status <> 'HOMOLOGADO'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3. Resultado acadêmico do ano 2025 → homologado ──
-- Marca snapshot_json com origem da migration para o rollback poder reverter.
SET @sql := IF(@has_res = 0, 'SELECT 1',
  "UPDATE resultado_academico
   SET snapshot_json = CASE
         WHEN snapshot_json IS NULL OR snapshot_json = '' THEN
           JSON_OBJECT('_migration_2026_10_09', 1, '_status_anterior', status)
         WHEN JSON_VALID(snapshot_json) THEN
           JSON_SET(snapshot_json, '$._migration_2026_10_09', 1, '$._status_anterior', status)
         ELSE snapshot_json
       END,
       status = 'homologado',
       homologado_em = COALESCE(homologado_em, NOW()),
       homologado_por = COALESCE(homologado_por, @admin)
   WHERE ano_letivo = 2025
     AND periodo_tipo = 'ano'
     AND periodo_numero = 0
     AND status <> 'homologado'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4. Remove emissões oficiais de documentos finais de 2025 ──
-- (ficha, ata/consolidado, boletim, histórico e demais tipagens do ano)
SET @sql := IF(@has_emis = 0, 'SELECT 1',
  "DELETE FROM resultado_documento_emissoes WHERE ano_letivo = 2025");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 5. Cancela históricos emitidos dos alunos das turmas 2025 ──
-- (permite emitir de novo no fluxo de impressão em lote)
SET @sql := IF(@has_hist = 0 OR @has_turmas = 0, 'SELECT 1',
  "UPDATE historico_documentos h
   INNER JOIN alunos a ON a.id = h.aluno_id
   INNER JOIN turmas t ON t.id = a.turma_id AND t.ano_letivo = 2025
   SET h.status = 'Cancelado',
       h.observacoes_gerais = CONCAT(
         IFNULL(NULLIF(h.observacoes_gerais, ''), ''),
         IF(IFNULL(h.observacoes_gerais, '') = '', '', ' | '),
         'Cancelado pela migration 2026_10_09 para regenerar documentos finais'
       ),
       h.updated_at = NOW()
   WHERE h.status IN ('Emitido', 'Assinado', 'Entregue', 'Conferido')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Também via matrícula, se existir
SET @has_mat := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matricula'
);
SET @has_al := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'ano_letivo'
);
SET @sql := IF(@has_hist = 0 OR @has_mat = 0 OR @has_al = 0 OR @has_turmas = 0, 'SELECT 1',
  "UPDATE historico_documentos h
   INNER JOIN matricula m ON m.aluno_id = h.aluno_id
   INNER JOIN ano_letivo al ON al.id = m.ano_letivo_id AND al.ano = 2025
   INNER JOIN turmas t ON t.id = m.turma_id AND t.ano_letivo = 2025
   SET h.status = 'Cancelado',
       h.observacoes_gerais = CONCAT(
         IFNULL(NULLIF(h.observacoes_gerais, ''), ''),
         IF(IFNULL(h.observacoes_gerais, '') = '', '', ' | '),
         'Cancelado pela migration 2026_10_09 para regenerar documentos finais'
       ),
       h.updated_at = NOW()
   WHERE h.status IN ('Emitido', 'Assinado', 'Entregue', 'Conferido')
     AND m.status IN ('ativa', 'concluido', 'transferido')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Remove assinaturas dos históricos cancelados nesta rodada (opcional / limpeza)
SET @sql := IF(@has_hist = 0 OR @has_hist_ass = 0, 'SELECT 1',
  "DELETE a FROM historico_assinaturas a
   INNER JOIN historico_documentos h ON h.id = a.historico_id
   WHERE h.status = 'Cancelado'
     AND h.observacoes_gerais LIKE '%migration 2026_10_09%'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 6. Remove índice de PDFs do aluno ligados a documentos finais de 2025 ──
SET @sql := IF(@has_pdfs = 0, 'SELECT 1',
  "DELETE FROM aluno_pdfs_emitidos
   WHERE tipo IN (
       'ficha', 'ficha_individual', 'historico', 'ata', 'ata_resultados',
       'resultado', 'boletim', 'consolidado', 'fechamento'
     )
     AND (
       titulo LIKE '%2025%'
       OR arquivo_key LIKE '2025/%'
       OR arquivo_key LIKE '%/2025/%'
     )");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
