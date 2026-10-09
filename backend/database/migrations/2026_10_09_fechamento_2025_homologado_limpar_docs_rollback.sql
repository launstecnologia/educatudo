-- Rollback de 2026_10_09_fechamento_2025_homologado_limpar_docs.sql
--
-- NÃO restaura:
-- - linhas apagadas de resultado_documento_emissoes
-- - linhas apagadas de aluno_pdfs_emitidos / historico_assinaturas
-- - snapshots/PDFs já emitidos
--
-- Reverte o estado canônico forçado pela migration (fechamento, resultado
-- acadêmico marcado e histórico cancelado com a marcação desta migration).

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @db := DATABASE();

SET @has_fech := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'fechamento_periodo'
);
SET @has_fech_hist := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'fechamento_periodo_historico'
);
SET @has_hist := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'historico_documentos'
);
SET @has_res := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'resultado_academico'
);

-- Restaura status do fechamento a partir do payload da auditoria
SET @sql := IF(@has_fech = 0 OR @has_fech_hist = 0, 'SELECT 1',
  "UPDATE fechamento_periodo f
   INNER JOIN fechamento_periodo_historico h
     ON h.fechamento_id = f.id
    AND h.justificativa LIKE 'Migration 2026_10_09%'
   SET f.status = COALESCE(
         NULLIF(JSON_UNQUOTE(JSON_EXTRACT(h.payload_json, '$.status_anterior')), ''),
         NULLIF(h.status_anterior, ''),
         'EM_FECHAMENTO'
       ),
       f.justificativa = TRIM(BOTH ' |' FROM REPLACE(
         IFNULL(f.justificativa, ''),
         'Migration 2026_10_09: força HOMOLOGADO 2025',
         ''
       )),
       f.updated_at = NOW()
   WHERE f.ano_letivo = 2025
     AND f.periodo_tipo = 'ano'
     AND f.periodo_numero = 0
     AND f.vigente = 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Remove auditoria gravada por esta migration
SET @sql := IF(@has_fech_hist = 0, 'SELECT 1',
  "DELETE FROM fechamento_periodo_historico
   WHERE justificativa LIKE 'Migration 2026_10_09%'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Remove fechamentos criados só por esta migration (justificativa exclusiva)
SET @sql := IF(@has_fech = 0, 'SELECT 1',
  "DELETE FROM fechamento_periodo
   WHERE ano_letivo = 2025
     AND periodo_tipo = 'ano'
     AND periodo_numero = 0
     AND vigente = 1
     AND justificativa LIKE 'Migration 2026_10_09: homologa 2025%'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Reverte resultado acadêmico marcado pela migration
SET @sql := IF(@has_res = 0, 'SELECT 1',
  "UPDATE resultado_academico
   SET status = COALESCE(
         NULLIF(JSON_UNQUOTE(JSON_EXTRACT(snapshot_json, '$._status_anterior')), ''),
         'em_andamento'
       ),
       snapshot_json = CASE
         WHEN JSON_VALID(snapshot_json) THEN
           JSON_REMOVE(snapshot_json, '$._migration_2026_10_09', '$._status_anterior')
         ELSE snapshot_json
       END
   WHERE ano_letivo = 2025
     AND periodo_tipo = 'ano'
     AND periodo_numero = 0
     AND JSON_VALID(snapshot_json)
     AND JSON_EXTRACT(snapshot_json, '$._migration_2026_10_09') IS NOT NULL");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Reabre históricos cancelados por esta migration (sem recriar assinaturas)
SET @sql := IF(@has_hist = 0, 'SELECT 1',
  "UPDATE historico_documentos
   SET status = 'Rascunho',
       observacoes_gerais = TRIM(BOTH ' |' FROM REPLACE(
         IFNULL(observacoes_gerais, ''),
         'Cancelado pela migration 2026_10_09 para regenerar documentos finais',
         ''
       )),
       updated_at = NOW()
   WHERE status = 'Cancelado'
     AND observacoes_gerais LIKE '%migration 2026_10_09%'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
