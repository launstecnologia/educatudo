-- Rollback do formato de papel / margem / espaçamento nos modelos.
-- Forward: 2026_08_22_modelos_documentos_papel.sql
-- DROP COLUMN justificado: reverte colunas desta migration; dados de papel voltam ao padrão A4 do código.

SET @db := DATABASE();
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos' AND COLUMN_NAME='espacamento_linha');
SET @sql := IF(@has>0 AND @col>0,
  "ALTER TABLE `secretaria_modelos_documentos` DROP COLUMN `espacamento_linha`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos' AND COLUMN_NAME='margem_mm');
SET @sql := IF(@has>0 AND @col>0,
  "ALTER TABLE `secretaria_modelos_documentos` DROP COLUMN `margem_mm`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos' AND COLUMN_NAME='formato_papel');
SET @sql := IF(@has>0 AND @col>0,
  "ALTER TABLE `secretaria_modelos_documentos` DROP COLUMN `formato_papel`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
