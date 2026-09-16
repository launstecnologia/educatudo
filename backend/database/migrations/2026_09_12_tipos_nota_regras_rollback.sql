-- Rollback de 2026_09_12_tipos_nota_regras.sql

SET @db := DATABASE();

SET @fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db
    AND CONSTRAINT_NAME = 'fk_notas_tipo_finais_tipo'
    AND TABLE_NAME = 'notas_tipo_finais'
);
SET @sql := IF(
  @fk > 0,
  'ALTER TABLE `notas_tipo_finais` DROP FOREIGN KEY `fk_notas_tipo_finais_tipo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `notas_tipo_finais`;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'media_professores_mesmo_componente'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `provas_tipos_avaliacao` DROP COLUMN `media_professores_mesmo_componente`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'quantidade_eventos_esperada'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `provas_tipos_avaliacao` DROP COLUMN `quantidade_eventos_esperada`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'escala_max'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `provas_tipos_avaliacao` DROP COLUMN `escala_max`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'criterio_fechamento'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `provas_tipos_avaliacao` DROP COLUMN `criterio_fechamento`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'registro_evento'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `provas_tipos_avaliacao` DROP COLUMN `registro_evento`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'origem'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `provas_tipos_avaliacao` DROP COLUMN `origem`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
