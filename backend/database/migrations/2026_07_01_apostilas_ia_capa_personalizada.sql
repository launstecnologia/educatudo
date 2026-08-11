-- Adiciona campo para capa personalizada (upload pelo admin) nas apostilas IA/Meu Material
-- Idempotente via INFORMATION_SCHEMA (MySQL não suporta ADD COLUMN IF NOT EXISTS)
SET @dbname = DATABASE();
SET @tbl = 'apostilas_ia';
SET @col = 'capa_personalizada';
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tbl AND COLUMN_NAME = @col) = 0,
    'ALTER TABLE apostilas_ia ADD COLUMN capa_personalizada VARCHAR(500) NULL DEFAULT NULL',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
