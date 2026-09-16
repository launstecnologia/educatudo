-- Ritmo do Quadro de Notas: intervalo e data de início (gerador semanal).
-- Sem escola_id (isolamento por PDO). Idempotente.
-- Rollback: 2026_09_11_quadros_ritmo_rollback.sql

SET @db := DATABASE();

SET @tgt := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas' AND TABLE_TYPE='BASE TABLE')>0,
  'quadros_notas',
  IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas' AND TABLE_TYPE='BASE TABLE')>0,
     'grupos_regras_notas', NULL)
);

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='ritmo_intervalo_semanas');
SET @sql := IF(@tgt IS NOT NULL AND @col=0,
  CONCAT('ALTER TABLE `', @tgt, '` ADD COLUMN `ritmo_intervalo_semanas` TINYINT UNSIGNED NOT NULL DEFAULT 2 COMMENT ''Intervalo em semanas entre colunas geradas'''),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='ritmo_data_inicio');
SET @sql := IF(@tgt IS NOT NULL AND @col=0,
  CONCAT('ALTER TABLE `', @tgt, '` ADD COLUMN `ritmo_data_inicio` DATE NULL DEFAULT NULL COMMENT ''Início do ritmo no modo simples'''),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @bl := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas_blocos' AND TABLE_TYPE='BASE TABLE')>0,
  'quadros_notas_blocos',
  IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_tipos' AND TABLE_TYPE='BASE TABLE')>0,
     'grupos_regras_notas_tipos', NULL)
);

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@bl AND COLUMN_NAME='ritmo_data_inicio');
SET @sql := IF(@bl IS NOT NULL AND @col=0,
  CONCAT('ALTER TABLE `', @bl, '` ADD COLUMN `ritmo_data_inicio` DATE NULL DEFAULT NULL COMMENT ''Início do ritmo deste bloco (S1/S3…)'''),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Recria views SELECT * para incluir as colunas novas.
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas');
SET @isview := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas');
SET @sql := IF(@has>0 AND @isview>0, "DROP VIEW `grupos_regras_notas`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @existsOld := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas');
SET @sql := IF(@has>0 AND @existsOld=0, "CREATE VIEW `grupos_regras_notas` AS SELECT * FROM `quadros_notas`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas_blocos');
SET @isview := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_tipos');
SET @sql := IF(@has>0 AND @isview>0, "DROP VIEW `grupos_regras_notas_tipos`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @existsOld := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_tipos');
SET @sql := IF(@has>0 AND @existsOld=0, "CREATE VIEW `grupos_regras_notas_tipos` AS SELECT * FROM `quadros_notas_blocos`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
