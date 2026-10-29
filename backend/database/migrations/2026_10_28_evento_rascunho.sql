-- Evento copiado de outro período entra como rascunho até ser publicado.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_28_evento_rascunho_rollback.sql

SET @db := DATABASE();
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'eventos_avaliativos'
    AND COLUMN_NAME = 'rascunho'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `eventos_avaliativos`
     ADD COLUMN `rascunho` TINYINT(1) NOT NULL DEFAULT 0
       COMMENT ''1 = cópia ainda não publicada''
     AFTER `ativo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
