-- Remove colunas de inativação do módulo Arquivos.
SET @schema := DATABASE();

SET @has_idx := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'modulos_arquivos' AND INDEX_NAME = 'idx_modulos_arquivos_ativo'
);
SET @sql_idx := IF(@has_idx > 0,
  'ALTER TABLE `modulos_arquivos` DROP KEY `idx_modulos_arquivos_ativo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_por := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'modulos_arquivos' AND COLUMN_NAME = 'inativado_por'
);
SET @sql_por := IF(@has_por > 0,
  'ALTER TABLE `modulos_arquivos` DROP COLUMN `inativado_por`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_por;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_em := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'modulos_arquivos' AND COLUMN_NAME = 'inativado_em'
);
SET @sql_em := IF(@has_em > 0,
  'ALTER TABLE `modulos_arquivos` DROP COLUMN `inativado_em`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_em;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_ativo := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'modulos_arquivos' AND COLUMN_NAME = 'ativo'
);
SET @sql_ativo := IF(@has_ativo > 0,
  'ALTER TABLE `modulos_arquivos` DROP COLUMN `ativo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql_ativo;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
