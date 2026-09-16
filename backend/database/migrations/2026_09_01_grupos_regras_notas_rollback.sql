-- Rollback de 2026_09_01_grupos_regras_notas.sql
-- Remove o vínculo nos eventos de prova e as tabelas do cadastro.

SET @db := DATABASE();

SET @fk_m := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_provas_blocos_grupo_regras_marca'
);
SET @sql := IF(
  @fk_m > 0,
  'ALTER TABLE `provas_blocos` DROP FOREIGN KEY `fk_provas_blocos_grupo_regras_marca`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_t := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_provas_blocos_grupo_regras_tipo'
);
SET @sql := IF(
  @fk_t > 0,
  'ALTER TABLE `provas_blocos` DROP FOREIGN KEY `fk_provas_blocos_grupo_regras_tipo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_g := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_provas_blocos_grupo_regras'
);
SET @sql := IF(
  @fk_g > 0,
  'ALTER TABLE `provas_blocos` DROP FOREIGN KEY `fk_provas_blocos_grupo_regras`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_marca := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'grupo_regras_marca_id'
);
SET @sql := IF(
  @col_marca > 0,
  'ALTER TABLE `provas_blocos` DROP COLUMN `grupo_regras_marca_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_tipo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'grupo_regras_tipo_id'
);
SET @sql := IF(
  @col_tipo > 0,
  'ALTER TABLE `provas_blocos` DROP COLUMN `grupo_regras_tipo_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_grupo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'grupo_regras_notas_id'
);
SET @sql := IF(
  @col_grupo > 0,
  'ALTER TABLE `provas_blocos` DROP COLUMN `grupo_regras_notas_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `grupos_regras_notas_tipo_materias`;
DROP TABLE IF EXISTS `grupos_regras_notas_tipo_marcas`;
DROP TABLE IF EXISTS `grupos_regras_notas_marcas`;
DROP TABLE IF EXISTS `grupos_regras_notas_tipos`;
DROP TABLE IF EXISTS `grupos_regras_notas`;
