-- Número de registro SED/GDAE no histórico oficial (Campo 6 do modelo SP).
-- Idempotente.

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'historico_documentos' AND COLUMN_NAME = 'numero_registro_sed'
);
SET @sql := IF(
  @col = 0 AND (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'historico_documentos'
  ) > 0,
  'ALTER TABLE historico_documentos ADD COLUMN numero_registro_sed VARCHAR(80) NULL AFTER observacoes_gerais',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
