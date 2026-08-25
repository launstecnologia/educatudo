-- Rollback: 2026_08_18_matriz_curricular.sql

SET @db := DATABASE();

-- Remove a FK e a coluna de turmas primeiro (dependem de matrizes_curriculares)
SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND CONSTRAINT_NAME='fk_turmas_matriz_curricular' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk>0, "ALTER TABLE `turmas` DROP FOREIGN KEY `fk_turmas_matriz_curricular`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND COLUMN_NAME='matriz_curricular_id');
SET @sql := IF(@col>0, "ALTER TABLE `turmas` DROP COLUMN `matriz_curricular_id`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Remove as tabelas (componentes primeiro, por causa da FK pra matrizes_curriculares)
SET @has_mcc := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matrizes_curriculares_componentes');
SET @sql := IF(@has_mcc>0, "DROP TABLE `matrizes_curriculares_componentes`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_matrizes := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matrizes_curriculares');
SET @sql := IF(@has_matrizes>0, "DROP TABLE `matrizes_curriculares`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
