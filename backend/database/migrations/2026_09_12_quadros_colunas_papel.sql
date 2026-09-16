-- Colunas do Quadro de Notas: papel (lançamento/calculada), tipo de nota, fórmula e boletim.
-- Sem escola_id (isolamento por PDO). Idempotente.
-- Rollback: 2026_09_12_quadros_colunas_papel_rollback.sql

SET @db := DATABASE();

SET @tgt := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas_colunas' AND TABLE_TYPE='BASE TABLE')>0,
  'quadros_notas_colunas',
  IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_marcas' AND TABLE_TYPE='BASE TABLE')>0,
     'grupos_regras_notas_marcas', NULL)
);

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='papel');
SET @sql := IF(@tgt IS NOT NULL AND @col=0,
  CONCAT('ALTER TABLE `', @tgt, '` ADD COLUMN `papel` VARCHAR(20) NOT NULL DEFAULT ''lancamento'' COMMENT ''lancamento|calculada'''),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='tipo_nota_id');
SET @sql := IF(@tgt IS NOT NULL AND @col=0,
  CONCAT('ALTER TABLE `', @tgt, '` ADD COLUMN `tipo_nota_id` INT UNSIGNED NULL DEFAULT NULL COMMENT ''provas_tipos_avaliacao.id (sem FK)'''),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='formula_json');
SET @sql := IF(@tgt IS NOT NULL AND @col=0,
  CONCAT('ALTER TABLE `', @tgt, '` ADD COLUMN `formula_json` TEXT NULL DEFAULT NULL COMMENT ''{"modo":"media","colunas":["s1","s3"]}'''),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='vai_para_boletim');
SET @sql := IF(@tgt IS NOT NULL AND @col=0,
  CONCAT('ALTER TABLE `', @tgt, '` ADD COLUMN `vai_para_boletim` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = coluna lida pelo boletim oficial'''),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Recria a view SELECT * para incluir as colunas novas.
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas_colunas');
SET @isview := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_marcas');
SET @sql := IF(@has>0 AND @isview>0, "DROP VIEW `grupos_regras_notas_marcas`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @existsOld := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_marcas');
SET @sql := IF(@has>0 AND @existsOld=0, "CREATE VIEW `grupos_regras_notas_marcas` AS SELECT * FROM `quadros_notas_colunas`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
