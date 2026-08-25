-- Rollback de 2026_08_22_regras_academicas.sql
-- Remove FKs e tabelas de regras acadêmicas. Tenant.

SET @db := DATABASE();

SET @fk_matriz := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='regras_academicas' AND CONSTRAINT_NAME='fk_regras_acad_matriz' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk_matriz>0, "ALTER TABLE `regras_academicas` DROP FOREIGN KEY `fk_regras_acad_matriz`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_mat := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='regras_academicas' AND CONSTRAINT_NAME='fk_regras_acad_materia' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk_mat>0, "ALTER TABLE `regras_academicas` DROP FOREIGN KEY `fk_regras_acad_materia`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_serie := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='regras_academicas' AND CONSTRAINT_NAME='fk_regras_acad_serie' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk_serie>0, "ALTER TABLE `regras_academicas` DROP FOREIGN KEY `fk_regras_acad_serie`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_curso := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='regras_academicas' AND CONSTRAINT_NAME='fk_regras_acad_curso' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk_curso>0, "ALTER TABLE `regras_academicas` DROP FOREIGN KEY `fk_regras_acad_curso`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_hist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='regras_academicas_historico');
SET @sql := IF(@has_hist>0, "DROP TABLE `regras_academicas_historico`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_regras := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='regras_academicas');
SET @sql := IF(@has_regras>0, "DROP TABLE `regras_academicas`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
