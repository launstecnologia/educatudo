-- Quantidade de dias letivos de cada período do ano (bimestre, trimestre, semestre ou etapa).
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_28_ano_letivo_periodos_dias_letivos_rollback.sql

SET @db := DATABASE();
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'ano_letivo_periodos'
    AND COLUMN_NAME = 'dias_letivos'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `ano_letivo_periodos`
     ADD COLUMN `dias_letivos` SMALLINT UNSIGNED NULL DEFAULT NULL
       COMMENT ''Quantidade de dias letivos do período''
     AFTER `data_fim`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
