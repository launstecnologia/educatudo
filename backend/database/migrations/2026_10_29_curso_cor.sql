-- Cor do curso/etapa/ciclo, usada no calendário letivo.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_29_curso_cor_rollback.sql

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'curso'
    AND COLUMN_NAME = 'cor'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `curso` ADD COLUMN `cor` VARCHAR(7) NULL DEFAULT NULL COMMENT ''Cor #rrggbb do curso no calendário letivo'' AFTER `descricao`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
