-- Layout da prova impressa: cabeçalho, questões, imagens e paginação.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_11_03_layout_prova_impressa_rollback.sql

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'provas_blocos'
    AND COLUMN_NAME = 'layout_impressao'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `provas_blocos` ADD COLUMN `layout_impressao` LONGTEXT NULL DEFAULT NULL COMMENT ''JSON do layout da prova impressa'' AFTER `formato_evento`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
