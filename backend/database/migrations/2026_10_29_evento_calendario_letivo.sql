-- Liga o compromisso do calendário letivo ao evento avaliativo que o gerou.
-- Assim, ao editar ou excluir o evento, o calendário do curso acompanha.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_29_evento_calendario_letivo_rollback.sql

SET @db := DATABASE();
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'calendario_letivo_eventos'
    AND COLUMN_NAME = 'evento_avaliativo_id'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `calendario_letivo_eventos`
     ADD COLUMN `evento_avaliativo_id` INT UNSIGNED NULL DEFAULT NULL
       COMMENT ''Evento avaliativo que publicou este compromisso''
     AFTER `calendario_id`,
     ADD KEY `idx_cal_eventos_avaliativo` (`evento_avaliativo_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
