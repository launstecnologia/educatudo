-- Rollback da coluna estrutura_json dos modelos de documento.
-- Forward: 2026_08_22_modelos_documentos_estrutura.sql
-- DROP COLUMN justificado: reverte só esta migration; o HTML legado (cabecalho/corpo/rodape) permanece.

SET @db := DATABASE();
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos' AND COLUMN_NAME='estrutura_json');
SET @sql := IF(@has>0 AND @col>0,
  "ALTER TABLE `secretaria_modelos_documentos` DROP COLUMN `estrutura_json`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
