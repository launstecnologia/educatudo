-- Campo para URL de gravação do Jitsi self-hosted (enviada via webhook do Jibri)
SET @schema := DATABASE();

SET @has_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'aulas_online' AND COLUMN_NAME = 'link_gravacao'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN link_gravacao VARCHAR(1200) NULL COMMENT "URL da gravação Jitsi/Jibri enviada via webhook"',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
