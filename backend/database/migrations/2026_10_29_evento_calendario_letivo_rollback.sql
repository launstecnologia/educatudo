-- Rollback de 2026_10_29_evento_calendario_letivo.sql
-- Remove o vínculo. Os compromissos já gravados no calendário permanecem.

SET @db := DATABASE();
SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'calendario_letivo_eventos'
    AND INDEX_NAME = 'idx_cal_eventos_avaliativo'
);
SET @sql_idx := IF(
  @idx > 0,
  'ALTER TABLE `calendario_letivo_eventos` DROP INDEX `idx_cal_eventos_avaliativo`',
  'SELECT 1'
);
PREPARE stmt_idx FROM @sql_idx; EXECUTE stmt_idx; DEALLOCATE PREPARE stmt_idx;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'calendario_letivo_eventos'
    AND COLUMN_NAME = 'evento_avaliativo_id'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `calendario_letivo_eventos` DROP COLUMN `evento_avaliativo_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
