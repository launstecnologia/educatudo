-- Fluxo resiliente de integração Panda em aulas_online
-- Idempotente para ambientes onde a tabela já existe.

SET @schema := DATABASE();

-- gerar_panda
SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema
    AND TABLE_NAME = 'aulas_online'
    AND COLUMN_NAME = 'gerar_panda'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN gerar_panda TINYINT(1) NOT NULL DEFAULT 0 AFTER publicado',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- panda_integracao_status
SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema
    AND TABLE_NAME = 'aulas_online'
    AND COLUMN_NAME = 'panda_integracao_status'
);
SET @sql := IF(@has_col = 0,
  "ALTER TABLE aulas_online ADD COLUMN panda_integracao_status VARCHAR(30) NOT NULL DEFAULT 'nao_solicitada' AFTER gerar_panda",
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- panda_integracao_erro
SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema
    AND TABLE_NAME = 'aulas_online'
    AND COLUMN_NAME = 'panda_integracao_erro'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN panda_integracao_erro TEXT NULL AFTER panda_integracao_status',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- panda_integracao_tentativas
SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema
    AND TABLE_NAME = 'aulas_online'
    AND COLUMN_NAME = 'panda_integracao_tentativas'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN panda_integracao_tentativas INT NOT NULL DEFAULT 0 AFTER panda_integracao_erro',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- panda_integracao_ultima_tentativa_em
SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema
    AND TABLE_NAME = 'aulas_online'
    AND COLUMN_NAME = 'panda_integracao_ultima_tentativa_em'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN panda_integracao_ultima_tentativa_em DATETIME NULL AFTER panda_integracao_tentativas',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- panda_live_id
SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema
    AND TABLE_NAME = 'aulas_online'
    AND COLUMN_NAME = 'panda_live_id'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN panda_live_id VARCHAR(120) NULL AFTER panda_integracao_ultima_tentativa_em',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- panda_stream_key_id
SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema
    AND TABLE_NAME = 'aulas_online'
    AND COLUMN_NAME = 'panda_stream_key_id'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN panda_stream_key_id VARCHAR(120) NULL AFTER panda_live_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- panda_live_player
SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema
    AND TABLE_NAME = 'aulas_online'
    AND COLUMN_NAME = 'panda_live_player'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN panda_live_player VARCHAR(700) NULL AFTER panda_stream_key_id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- panda_live_hls
SET @has_col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema
    AND TABLE_NAME = 'aulas_online'
    AND COLUMN_NAME = 'panda_live_hls'
);
SET @sql := IF(@has_col = 0,
  'ALTER TABLE aulas_online ADD COLUMN panda_live_hls VARCHAR(700) NULL AFTER panda_live_player',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índices úteis
SET @has_idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema
    AND TABLE_NAME = 'aulas_online'
    AND INDEX_NAME = 'idx_aulas_online_panda_status'
);
SET @sql := IF(@has_idx = 0,
  'ALTER TABLE aulas_online ADD INDEX idx_aulas_online_panda_status (panda_integracao_status)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
