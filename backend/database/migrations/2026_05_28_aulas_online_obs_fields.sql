-- Campos OBS para aulas_online (idempotente)
SET @schema := DATABASE();

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'aulas_online' AND COLUMN_NAME = 'panda_rtmp'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN panda_rtmp VARCHAR(255) NULL AFTER panda_live_hls',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'aulas_online' AND COLUMN_NAME = 'panda_stream_key'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN panda_stream_key VARCHAR(255) NULL AFTER panda_rtmp',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
