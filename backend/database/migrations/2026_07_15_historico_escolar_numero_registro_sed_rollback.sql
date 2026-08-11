-- Rollback: remove numero_registro_sed de historico_documentos.

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'historico_documentos' AND COLUMN_NAME = 'numero_registro_sed'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE historico_documentos DROP COLUMN numero_registro_sed',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
