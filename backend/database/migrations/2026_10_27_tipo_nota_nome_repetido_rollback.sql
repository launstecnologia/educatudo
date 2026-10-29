-- Volta a exigir nome único em provas_tipos_avaliacao.
-- Falha se já existirem dois tipos ativos ou excluídos com o mesmo nome.
-- Tenant.

SET @db := DATABASE();

SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provas_tipos_avaliacao'
    AND INDEX_NAME = 'idx_provas_tipos_avaliacao_nome'
);
SET @sql_drop_idx := IF(
  @has_idx > 0,
  'ALTER TABLE `provas_tipos_avaliacao` DROP INDEX `idx_provas_tipos_avaliacao_nome`',
  'SELECT 1'
);
PREPARE stmt_drop_idx FROM @sql_drop_idx;
EXECUTE stmt_drop_idx;
DEALLOCATE PREPARE stmt_drop_idx;

SET @has_uk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provas_tipos_avaliacao'
    AND INDEX_NAME = 'uk_provas_tipos_avaliacao_nome'
);
SET @sql_uk := IF(
  @has_uk = 0,
  'ALTER TABLE `provas_tipos_avaliacao` ADD UNIQUE KEY `uk_provas_tipos_avaliacao_nome` (`nome`)',
  'SELECT 1'
);
PREPARE stmt_uk FROM @sql_uk;
EXECUTE stmt_uk;
DEALLOCATE PREPARE stmt_uk;
