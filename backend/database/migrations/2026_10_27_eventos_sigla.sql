-- Sigla curta do evento avaliativo, usada na fórmula das notas.
-- Ex.: Prova Bimestral → PB. O nome completo permanece no demonstrativo.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_27_eventos_sigla_rollback.sql

SET @db := DATABASE();
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'eventos_avaliativos'
    AND COLUMN_NAME = 'sigla'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `eventos_avaliativos`
     ADD COLUMN `sigla` VARCHAR(12) NULL DEFAULT NULL
       COMMENT ''Abreviação exibida na fórmula das notas. Ex.: PB''
     AFTER `nome`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
