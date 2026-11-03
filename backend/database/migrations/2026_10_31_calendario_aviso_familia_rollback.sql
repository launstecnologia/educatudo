-- Rollback de 2026_10_31_calendario_aviso_familia.sql
-- Remove o vínculo. Os avisos já publicados em school_calendar_events permanecem.
-- Os tipos reunião, passeio e apresentação saem do catálogo; eventos já gravados com esses slugs permanecem.

SET @db := DATABASE();

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'calendario_letivo_eventos'
    AND INDEX_NAME = 'idx_cal_eventos_escolar'
);
SET @sql_idx := IF(
  @idx > 0,
  'ALTER TABLE `calendario_letivo_eventos` DROP INDEX `idx_cal_eventos_escolar`',
  'SELECT 1'
);
PREPARE stmt_idx FROM @sql_idx; EXECUTE stmt_idx; DEALLOCATE PREPARE stmt_idx;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'calendario_letivo_eventos'
    AND COLUMN_NAME = 'evento_escolar_id'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `calendario_letivo_eventos` DROP COLUMN `evento_escolar_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_tipos := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo_tipos'
);
SET @sql_tipos := IF(
  @has_tipos > 0,
  "DELETE FROM `calendario_letivo_tipos` WHERE `slug` IN ('reuniao', 'passeio', 'apresentacao') AND `sistema` = 1",
  'SELECT 1'
);
PREPARE stmt_tipos FROM @sql_tipos; EXECUTE stmt_tipos; DEALLOCATE PREPARE stmt_tipos;
