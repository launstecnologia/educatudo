-- Adiciona suporte a subpastas em modulos_arquivos_pastas
SET @has_col = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'modulos_arquivos_pastas'
      AND COLUMN_NAME  = 'parent_id'
);

SET @sql = IF(@has_col = 0,
    'ALTER TABLE modulos_arquivos_pastas ADD COLUMN parent_id INT NULL DEFAULT NULL AFTER id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
