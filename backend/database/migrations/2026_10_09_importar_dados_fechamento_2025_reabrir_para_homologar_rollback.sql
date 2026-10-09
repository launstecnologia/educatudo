-- Rollback de 2026_10_09_importar_dados_fechamento_2025_reabrir_para_homologar.sql
--
-- NÃO restaura emissões/PDFs apagados.
-- Volta fechamento e resultado acadêmico marcados por esta migration.

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
SET @has_res := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'resultado_academico'
);
SET @has_hist := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'historico_documentos'
);

-- Restaura status do fechamento a partir da auditoria
SET @sql := IF(@has_fech = 0 OR @has_fech_hist = 0, 'SELECT 1',
  "UPDATE fechamento_periodo f
   INNER JOIN fechamento_periodo_historico h
     ON h.fechamento_id = f.id
    AND h.justificativa LIKE 'Migration 2026_10_09_reabrir%'
   SET f.status = COALESCE(
         NULLIF(JSON_UNQUOTE(JSON_EXTRACT(h.payload_json, '$.status_anterior')), ''),
         NULLIF(h.status_anterior, ''),
         'HOMOLOGADO'
       ),
       f.justificativa = TRIM(BOTH ' |' FROM REPLACE(
         IFNULL(f.justificativa, ''),
         'Migration 2026_10_09_reabrir: Em fechamento para testar homologação',
         ''
       )),
       f.updated_at = NOW()
   WHERE f.ano_letivo = 2025
     AND f.periodo_tipo = 'ano'
     AND f.periodo_numero = 0
     AND f.vigente = 1");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_fech_hist = 0, 'SELECT 1',
  "DELETE FROM fechamento_periodo_historico
   WHERE justificativa LIKE 'Migration 2026_10_09_reabrir%'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Reverte resultado acadêmico marcado
SET @sql := IF(@has_res = 0, 'SELECT 1',
  "UPDATE resultado_academico
   SET status = COALESCE(
         NULLIF(JSON_UNQUOTE(JSON_EXTRACT(snapshot_json, '$._status_anterior')), ''),
         'homologado'
       ),
       reaberto_em = NULL,
       reaberto_por = NULL,
       snapshot_json = CASE
         WHEN JSON_VALID(snapshot_json) THEN
           JSON_REMOVE(snapshot_json, '$._migration_2026_10_09_reabrir', '$._status_anterior')
         ELSE snapshot_json
       END
   WHERE ano_letivo = 2025
     AND periodo_tipo = 'ano'
     AND periodo_numero = 0
     AND JSON_VALID(snapshot_json)
     AND JSON_EXTRACT(snapshot_json, '$._migration_2026_10_09_reabrir') IS NOT NULL");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_hist = 0, 'SELECT 1',
  "UPDATE historico_documentos
   SET status = 'Rascunho',
       observacoes_gerais = TRIM(BOTH ' |' FROM REPLACE(
         IFNULL(observacoes_gerais, ''),
         'Cancelado pela migration 2026_10_09_reabrir para testar homologação',
         ''
       )),
       updated_at = NOW()
   WHERE status = 'Cancelado'
     AND observacoes_gerais LIKE '%migration 2026_10_09_reabrir%'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
