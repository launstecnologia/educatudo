-- Remove a informação particular do professor no calendário letivo.
-- Tenant. Idempotente.

SET @db := DATABASE();
SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'calendario_letivo_eventos'
    AND INDEX_NAME = 'idx_cal_eventos_professor'
);
SET @sql := IF(
  @idx > 0,
  'ALTER TABLE `calendario_letivo_eventos` DROP INDEX `idx_cal_eventos_professor`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'calendario_letivo_eventos'
    AND COLUMN_NAME = 'professor_id'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `calendario_letivo_eventos` DROP COLUMN `professor_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
