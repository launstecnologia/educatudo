-- Rollback de 2026_10_06_fechamento_apoio_digitacao.sql
-- Remove o histórico de apoio à digitação e os identificadores oficiais acrescentados.
-- Tenant. Idempotente.

SET @db := DATABASE();

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fechamento_apoio_digitacao');
SET @sql := IF(@has>0, 'DROP TABLE `fechamento_apoio_digitacao`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='unidades' AND COLUMN_NAME='codigo_estadual');
SET @sql := IF(@col>0, 'ALTER TABLE `unidades` DROP COLUMN `codigo_estadual`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND COLUMN_NAME='numero_classe_oficial');
SET @sql := IF(@col>0, 'ALTER TABLE `turmas` DROP COLUMN `numero_classe_oficial`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND COLUMN_NAME='codigo_curso_oficial');
SET @sql := IF(@col>0, 'ALTER TABLE `turmas` DROP COLUMN `codigo_curso_oficial`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos' AND COLUMN_NAME='cgm');
SET @sql := IF(@col>0, 'ALTER TABLE `alunos` DROP COLUMN `cgm`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos' AND COLUMN_NAME='ra_uf');
SET @sql := IF(@col>0, 'ALTER TABLE `alunos` DROP COLUMN `ra_uf`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos' AND COLUMN_NAME='ra_digito');
SET @sql := IF(@col>0, 'ALTER TABLE `alunos` DROP COLUMN `ra_digito`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
