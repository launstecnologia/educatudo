-- Rollback: remove ordem de eventos avaliativos e subeventos.
-- Tenant. Idempotente.

SET @db := DATABASE();

SET @idx_pb := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND INDEX_NAME = 'idx_provas_blocos_ordem_evento'
);
SET @sql := IF(@idx_pb > 0,
  'ALTER TABLE `provas_blocos` DROP INDEX `idx_provas_blocos_ordem_evento`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_pb := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'ordem'
);
SET @sql := IF(@col_pb > 0,
  'ALTER TABLE `provas_blocos` DROP COLUMN `ordem`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_ev := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'eventos_avaliativos' AND INDEX_NAME = 'idx_eventos_avaliativos_ordem'
);
SET @sql := IF(@idx_ev > 0,
  'ALTER TABLE `eventos_avaliativos` DROP INDEX `idx_eventos_avaliativos_ordem`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_ev := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'eventos_avaliativos' AND COLUMN_NAME = 'ordem'
);
SET @sql := IF(@col_ev > 0,
  'ALTER TABLE `eventos_avaliativos` DROP COLUMN `ordem`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
