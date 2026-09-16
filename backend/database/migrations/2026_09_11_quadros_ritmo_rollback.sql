-- Rollback: 2026_09_11_quadros_ritmo.sql

SET @db := DATABASE();

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas');
SET @isview := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas');
SET @sql := IF(@has>0 AND @isview>0, "DROP VIEW `grupos_regras_notas`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @hasb := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas_blocos');
SET @isviewb := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_tipos');
SET @sql := IF(@hasb>0 AND @isviewb>0, "DROP VIEW `grupos_regras_notas_tipos`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tgt := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas' AND TABLE_TYPE='BASE TABLE')>0,
  'quadros_notas',
  IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas' AND TABLE_TYPE='BASE TABLE')>0,
     'grupos_regras_notas', NULL)
);

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='ritmo_data_inicio');
SET @sql := IF(@tgt IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tgt, '` DROP COLUMN `ritmo_data_inicio`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='ritmo_intervalo_semanas');
SET @sql := IF(@tgt IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tgt, '` DROP COLUMN `ritmo_intervalo_semanas`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @bl := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas_blocos' AND TABLE_TYPE='BASE TABLE')>0,
  'quadros_notas_blocos',
  IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_tipos' AND TABLE_TYPE='BASE TABLE')>0,
     'grupos_regras_notas_tipos', NULL)
);

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@bl AND COLUMN_NAME='ritmo_data_inicio');
SET @sql := IF(@bl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @bl, '` DROP COLUMN `ritmo_data_inicio`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existsOld := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas');
SET @sql := IF(@has>0 AND @existsOld=0, "CREATE VIEW `grupos_regras_notas` AS SELECT * FROM `quadros_notas`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @existsOldb := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_tipos');
SET @sql := IF(@hasb>0 AND @existsOldb=0, "CREATE VIEW `grupos_regras_notas_tipos` AS SELECT * FROM `quadros_notas_blocos`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
