-- Reabre o fechamento do ano 2025 para o usuário testar o ciclo:
-- Em fechamento → Homologar → gerar documentos finais.
--
-- Tira HOMOLOGADO do painel, volta resultado acadêmico do ano para
-- em_andamento e limpa de novo as emissões/documentos finais.
--
-- Carga pontual (importar_dados_*): NÃO entra em “Executar todas” nem no bootstrap.
-- Rode só na escola de teste pelo Master (marcar este arquivo).
-- Tenant. Idempotente.
-- Rollback: 2026_10_09_importar_dados_fechamento_2025_reabrir_para_homologar_rollback.sql
-- Isolamento é pela conexão PDO; sem coluna escola_id.
-- DELETE de emissões: intencional — regenerar após homologar. Rollback não restaura PDFs.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @db := DATABASE();

SET @admin := COALESCE(
  (SELECT id FROM usuarios WHERE email = 'admin@educateste.local' LIMIT 1),
  (SELECT id FROM usuarios WHERE email = 'admin@educa.local' LIMIT 1),
  (SELECT id FROM usuarios WHERE tipo = 'admin_escola' ORDER BY id LIMIT 1)
);

SET @has_fech := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'fechamento_periodo'
);
SET @has_fech_hist := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'fechamento_periodo_historico'
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
SET @has_turmas := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'turmas'
);

-- ── 1. Auditoria: status anterior → EM_FECHAMENTO ──
SET @sql := IF(@has_fech = 0 OR @has_fech_hist = 0, 'SELECT 1',
  "INSERT INTO fechamento_periodo_historico
      (fechamento_id, status_anterior, status_novo, justificativa, usuario_id, payload_json)
   SELECT f.id, f.status, 'EM_FECHAMENTO',
          'Migration 2026_10_09_reabrir: reabre 2025 para testar homologação', @admin,
          JSON_OBJECT('status_anterior', f.status, 'origem', '2026_10_09_reabrir')
   FROM fechamento_periodo f
   WHERE f.ano_letivo = 2025
     AND f.periodo_tipo = 'ano'
     AND f.periodo_numero = 0
     AND f.vigente = 1
     AND f.status IN ('HOMOLOGADO', 'RETIFICADO')
     AND NOT EXISTS (
       SELECT 1 FROM fechamento_periodo_historico h
       WHERE h.fechamento_id = f.id
         AND h.justificativa LIKE 'Migration 2026_10_09_reabrir%'
     )");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. Painel: HOMOLOGADO/RETIFICADO → EM_FECHAMENTO (editável) ──
SET @sql := IF(@has_fech = 0, 'SELECT 1',
  "UPDATE fechamento_periodo
   SET status = 'EM_FECHAMENTO',
       homologado_em = NULL,
       homologado_por = NULL,
       justificativa = CONCAT(
         IFNULL(NULLIF(justificativa, ''), ''),
         IF(IFNULL(justificativa, '') = '', '', ' | '),
         'Migration 2026_10_09_reabrir: Em fechamento para testar homologação'
       ),
       updated_at = NOW()
   WHERE ano_letivo = 2025
     AND periodo_tipo = 'ano'
     AND periodo_numero = 0
     AND vigente = 1
     AND status IN ('HOMOLOGADO', 'RETIFICADO')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3. Resultado acadêmico do ano: homologado → em_andamento ──
SET @sql := IF(@has_res = 0, 'SELECT 1',
  "UPDATE resultado_academico
   SET snapshot_json = CASE
         WHEN snapshot_json IS NULL OR snapshot_json = '' THEN
           JSON_OBJECT('_migration_2026_10_09_reabrir', 1, '_status_anterior', status)
         WHEN JSON_VALID(snapshot_json) THEN
           JSON_SET(snapshot_json, '$._migration_2026_10_09_reabrir', 1, '$._status_anterior', status)
         ELSE snapshot_json
       END,
       status = 'em_andamento',
       homologado_em = NULL,
       homologado_por = NULL,
       reaberto_em = NOW(),
       reaberto_por = @admin
   WHERE ano_letivo = 2025
     AND periodo_tipo = 'ano'
     AND periodo_numero = 0
     AND status = 'homologado'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 4. Limpa documentos finais de 2025 (para gerar após homologar) ──
SET @sql := IF(@has_emis = 0, 'SELECT 1',
  "DELETE FROM resultado_documento_emissoes WHERE ano_letivo = 2025");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_hist = 0 OR @has_turmas = 0, 'SELECT 1',
  "UPDATE historico_documentos h
   INNER JOIN alunos a ON a.id = h.aluno_id
   INNER JOIN turmas t ON t.id = a.turma_id AND t.ano_letivo = 2025
   SET h.status = 'Cancelado',
       h.observacoes_gerais = CONCAT(
         IFNULL(NULLIF(h.observacoes_gerais, ''), ''),
         IF(IFNULL(h.observacoes_gerais, '') = '', '', ' | '),
         'Cancelado pela migration 2026_10_09_reabrir para testar homologação'
       ),
       h.updated_at = NOW()
   WHERE h.status IN ('Emitido', 'Assinado', 'Entregue', 'Conferido')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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
         'Cancelado pela migration 2026_10_09_reabrir para testar homologação'
       ),
       h.updated_at = NOW()
   WHERE h.status IN ('Emitido', 'Assinado', 'Entregue', 'Conferido')
     AND m.status IN ('ativa', 'concluido', 'transferido')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_hist = 0 OR @has_hist_ass = 0, 'SELECT 1',
  "DELETE a FROM historico_assinaturas a
   INNER JOIN historico_documentos h ON h.id = a.historico_id
   WHERE h.status = 'Cancelado'
     AND h.observacoes_gerais LIKE '%migration 2026_10_09_reabrir%'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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
