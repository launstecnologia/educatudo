-- Formato de papel (A4/A5), margem e espaçamento de linha nos modelos de documento.
-- Idempotente. Rollback: 2026_08_22_modelos_documentos_papel_rollback.sql

SET @db := DATABASE();
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos' AND COLUMN_NAME='formato_papel');
SET @sql := IF(@has>0 AND @col=0,
  "ALTER TABLE `secretaria_modelos_documentos`
     ADD COLUMN `formato_papel` ENUM('a4','a5') NOT NULL DEFAULT 'a4' AFTER `orientacao`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos' AND COLUMN_NAME='margem_mm');
SET @sql := IF(@has>0 AND @col=0,
  "ALTER TABLE `secretaria_modelos_documentos`
     ADD COLUMN `margem_mm` TINYINT UNSIGNED NOT NULL DEFAULT 20 AFTER `formato_papel`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos' AND COLUMN_NAME='espacamento_linha');
SET @sql := IF(@has>0 AND @col=0,
  "ALTER TABLE `secretaria_modelos_documentos`
     ADD COLUMN `espacamento_linha` DECIMAL(3,2) NOT NULL DEFAULT 1.50 AFTER `margem_mm`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
