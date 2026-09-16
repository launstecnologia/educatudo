-- Rollback: 2026_09_12_quadros_colunas_papel.sql
-- DROP VIEW: SELECT * da view antiga não inclui as colunas novas; recria depois do DROP COLUMN.
-- DROP COLUMN só se o COMMENT for o desta migration (não apaga coluna pré-existente de outro schema).

SET @db := DATABASE();

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas_colunas');
SET @isview := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_marcas');
SET @sql := IF(@has>0 AND @isview>0, "DROP VIEW `grupos_regras_notas_marcas`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tgt := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas_colunas' AND TABLE_TYPE='BASE TABLE')>0,
  'quadros_notas_colunas',
  IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_marcas' AND TABLE_TYPE='BASE TABLE')>0,
     'grupos_regras_notas_marcas', NULL)
);

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='vai_para_boletim' AND COLUMN_COMMENT='1 = coluna lida pelo boletim oficial');
SET @sql := IF(@tgt IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tgt, '` DROP COLUMN `vai_para_boletim`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='formula_json' AND COLUMN_COMMENT='{"modo":"media","colunas":["s1","s3"]}');
SET @sql := IF(@tgt IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tgt, '` DROP COLUMN `formula_json`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='tipo_nota_id' AND COLUMN_COMMENT='provas_tipos_avaliacao.id (sem FK)');
SET @sql := IF(@tgt IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tgt, '` DROP COLUMN `tipo_nota_id`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='papel' AND COLUMN_COMMENT='lancamento|calculada');
SET @sql := IF(@tgt IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tgt, '` DROP COLUMN `papel`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas_colunas');
SET @existsOld := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_marcas');
SET @sql := IF(@has>0 AND @existsOld=0, "CREATE VIEW `grupos_regras_notas_marcas` AS SELECT * FROM `quadros_notas_colunas`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
