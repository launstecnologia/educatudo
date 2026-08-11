-- Campos para gravação/VOD gerado após live Panda (idempotente)
SET @schema := DATABASE();

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'aulas_online' AND COLUMN_NAME = 'panda_recording_video_id'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN panda_recording_video_id VARCHAR(120) NULL AFTER panda_stream_key',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'aulas_online' AND COLUMN_NAME = 'panda_recording_player'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN panda_recording_player VARCHAR(700) NULL AFTER panda_recording_video_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'aulas_online' AND COLUMN_NAME = 'panda_recording_hls'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN panda_recording_hls VARCHAR(700) NULL AFTER panda_recording_player',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'aulas_online' AND COLUMN_NAME = 'panda_recording_synced_at'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN panda_recording_synced_at DATETIME NULL AFTER panda_recording_hls',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
