-- JSON do editor visual de modelos (fonte da verdade do layout).
-- Idempotente. Rollback: 2026_08_22_modelos_documentos_estrutura_rollback.sql

SET @db := DATABASE();
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos' AND COLUMN_NAME='estrutura_json');
SET @sql := IF(@has>0 AND @col=0,
  "ALTER TABLE `secretaria_modelos_documentos`
     ADD COLUMN `estrutura_json` MEDIUMTEXT NULL COMMENT 'JSON do editor visual (seções/colunas/elementos)' AFTER `rodape_html`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
