-- Campos para gravação JaaS/Jitsi em aulas_online (idempotente)
SET @schema := DATABASE();

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'aulas_online' AND COLUMN_NAME = 'jaas_recording_url'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN jaas_recording_url VARCHAR(1200) NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'aulas_online' AND COLUMN_NAME = 'jaas_recording_path'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN jaas_recording_path VARCHAR(700) NULL AFTER jaas_recording_url',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'aulas_online' AND COLUMN_NAME = 'jaas_recording_session_id'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN jaas_recording_session_id VARCHAR(120) NULL AFTER jaas_recording_path',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'aulas_online' AND COLUMN_NAME = 'jaas_recording_uploaded_at'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN jaas_recording_uploaded_at DATETIME NULL AFTER jaas_recording_session_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'aulas_online' AND COLUMN_NAME = 'jaas_recording_webhook_raw'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN jaas_recording_webhook_raw LONGTEXT NULL AFTER jaas_recording_uploaded_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
