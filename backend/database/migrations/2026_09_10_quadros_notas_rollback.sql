-- Rollback: 2026_09_10_quadros_notas.sql
-- Remove views de transição, colunas novas e devolve os nomes grupos_regras_notas*.

SET @db := DATABASE();

-- 1) Views primeiro (senão o RENAME esbarra no nome antigo).
DROP VIEW IF EXISTS `grupos_regras_notas`;
DROP VIEW IF EXISTS `grupos_regras_notas_tipos`;
DROP VIEW IF EXISTS `grupos_regras_notas_marcas`;
DROP VIEW IF EXISTS `grupos_regras_notas_tipo_marcas`;
DROP VIEW IF EXISTS `grupos_regras_notas_tipo_materias`;
DROP VIEW IF EXISTS `provas_blocos_grupos_regras`;

-- 2) Colunas novas (na tabela física, qualquer um dos dois nomes).
SET @tgt := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas' AND TABLE_TYPE='BASE TABLE')>0,
  'quadros_notas',
  IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas' AND TABLE_TYPE='BASE TABLE')>0,
     'grupos_regras_notas', NULL)
);

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='modo');
SET @sql := IF(@tgt IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tgt, '` DROP COLUMN `modo`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='coluna_consolidada_nome');
SET @sql := IF(@tgt IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tgt, '` DROP COLUMN `coluna_consolidada_nome`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='coluna_consolidada_codigo');
SET @sql := IF(@tgt IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tgt, '` DROP COLUMN `coluna_consolidada_codigo`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='criterio_calculo');
SET @sql := IF(@tgt IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tgt, '` DROP COLUMN `criterio_calculo`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='escala_max');
SET @sql := IF(@tgt IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tgt, '` DROP COLUMN `escala_max`'), 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Devolve os nomes físicos.
SET @new := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas' AND TABLE_TYPE='BASE TABLE');
SET @old := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas' AND TABLE_TYPE='BASE TABLE');
SET @sql := IF(@new>0 AND @old=0,
  "RENAME TABLE
     `quadros_notas_bloco_colunas` TO `grupos_regras_notas_tipo_marcas`,
     `quadros_notas_bloco_materias` TO `grupos_regras_notas_tipo_materias`,
     `quadros_notas_blocos` TO `grupos_regras_notas_tipos`,
     `quadros_notas_colunas` TO `grupos_regras_notas_marcas`,
     `quadros_notas` TO `grupos_regras_notas`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @newv := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='provas_blocos_quadros_notas' AND TABLE_TYPE='BASE TABLE');
SET @oldv := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='provas_blocos_grupos_regras' AND TABLE_TYPE='BASE TABLE');
SET @sql := IF(@newv>0 AND @oldv=0,
  "RENAME TABLE `provas_blocos_quadros_notas` TO `provas_blocos_grupos_regras`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
