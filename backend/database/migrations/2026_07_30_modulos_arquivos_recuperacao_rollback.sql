-- Remove coluna recuperacao do módulo de arquivos
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'modulos_arquivos'
      AND INDEX_NAME = 'idx_recuperacao'
);

SET @sql_idx := IF(
    @idx_exists > 0,
    'ALTER TABLE `modulos_arquivos` DROP KEY `idx_recuperacao`',
    'SELECT 1'
);

PREPARE stmt FROM @sql_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'modulos_arquivos'
      AND COLUMN_NAME = 'recuperacao'
);

SET @sql := IF(
    @col_exists > 0,
    'ALTER TABLE `modulos_arquivos` DROP COLUMN `recuperacao`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
