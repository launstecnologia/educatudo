-- Rollback de 2026_09_22_ano_letivo_periodo_tipo_varchar.sql
-- Volta periodo_tipo para ENUM. Valores fora do conjunto viram bimestre.

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'ano_letivo' AND COLUMN_NAME = 'periodo_tipo'
);

SET @sql := IF(
  @col > 0,
  "UPDATE `ano_letivo` SET `periodo_tipo` = 'bimestre'
   WHERE `periodo_tipo` NOT IN ('bimestre','trimestre','semestre','etapa_unica')",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @col > 0,
  "ALTER TABLE `ano_letivo` MODIFY COLUMN `periodo_tipo` ENUM('bimestre','trimestre','semestre','etapa_unica') NOT NULL DEFAULT 'bimestre' COMMENT 'Divisão do ano: 4/3/2/1 períodos'",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
