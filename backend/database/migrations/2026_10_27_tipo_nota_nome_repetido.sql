-- Evento avaliativo pode repetir o nome de um tipo de nota já existente.
-- O nome deixa de ser único em provas_tipos_avaliacao. A busca por nome continua indexada.
-- Tenant. Idempotente. Rollback: 2026_10_27_tipo_nota_nome_repetido_rollback.sql

SET @db := DATABASE();

SET @has_uk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provas_tipos_avaliacao'
    AND INDEX_NAME = 'uk_provas_tipos_avaliacao_nome'
);
SET @sql_drop := IF(
  @has_uk > 0,
  'ALTER TABLE `provas_tipos_avaliacao` DROP INDEX `uk_provas_tipos_avaliacao_nome`',
  'SELECT 1'
);
PREPARE stmt_drop FROM @sql_drop;
EXECUTE stmt_drop;
DEALLOCATE PREPARE stmt_drop;

SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provas_tipos_avaliacao'
    AND INDEX_NAME = 'idx_provas_tipos_avaliacao_nome'
);
SET @sql_idx := IF(
  @has_idx = 0,
  'ALTER TABLE `provas_tipos_avaliacao` ADD INDEX `idx_provas_tipos_avaliacao_nome` (`nome`)',
  'SELECT 1'
);
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;
