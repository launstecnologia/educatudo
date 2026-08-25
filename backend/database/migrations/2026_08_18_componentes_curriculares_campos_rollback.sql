-- Rollback: 2026_08_18_componentes_curriculares_campos.sql

SET @db := DATABASE();
SET @has_materias := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='ativo');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `ativo`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='permite_diario');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `permite_diario`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='permite_plano_aula');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `permite_plano_aula`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='permite_frequencia');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `permite_frequencia`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='permite_avaliacao');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `permite_avaliacao`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='ordem');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `ordem`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='cor');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `cor`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='descricao');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `descricao`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='tipo');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `tipo`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='area_conhecimento');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `area_conhecimento`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='sigla');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `sigla`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='codigo');
SET @sql := IF(@has_materias>0 AND @col>0, "ALTER TABLE materias DROP COLUMN `codigo`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
