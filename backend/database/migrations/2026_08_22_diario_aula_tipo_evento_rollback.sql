-- Rollback: 2026_08_22_diario_aula_tipo_evento.sql
-- Remove só as colunas/índice/FK novos. Não toca em plano_aula_id nem em frequências.

SET @db := DATABASE();

SET @has_aulas := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_aulas');

SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_aulas' AND CONSTRAINT_NAME='fk_diario_aulas_evento_bloco' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@has_aulas>0 AND @fk>0,
  "ALTER TABLE `diario_aulas` DROP FOREIGN KEY `fk_diario_aulas_evento_bloco`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_aulas' AND INDEX_NAME='idx_diario_evento_bloco');
SET @sql := IF(@has_aulas>0 AND @idx>0,
  "ALTER TABLE `diario_aulas` DROP KEY `idx_diario_evento_bloco`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_aulas' AND COLUMN_NAME='evento_bloco_id');
SET @sql := IF(@has_aulas>0 AND @col>0,
  "ALTER TABLE `diario_aulas` DROP COLUMN `evento_bloco_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_aulas' AND COLUMN_NAME='tipo_aula');
SET @sql := IF(@has_aulas>0 AND @col>0,
  "ALTER TABLE `diario_aulas` DROP COLUMN `tipo_aula`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
