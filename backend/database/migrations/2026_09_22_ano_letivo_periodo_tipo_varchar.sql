-- Garante periodo_tipo como VARCHAR para gravar trimestre/semestre sem depender do ENUM.
-- Tenant. Idempotente. Rollback: 2026_09_22_ano_letivo_periodo_tipo_varchar_rollback.sql

SET @db := DATABASE();

SET @has_ano := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'ano_letivo'
);

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'ano_letivo' AND COLUMN_NAME = 'periodo_tipo'
);

SET @sql := IF(
  @has_ano > 0 AND @col = 0,
  "ALTER TABLE `ano_letivo` ADD COLUMN `periodo_tipo` VARCHAR(20) NOT NULL DEFAULT 'bimestre' COMMENT 'Divisão do ano: bimestre/trimestre/semestre/etapa_unica' AFTER `data_fim`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tipo := (
  SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'ano_letivo' AND COLUMN_NAME = 'periodo_tipo'
);

SET @sql := IF(
  @has_ano > 0 AND @tipo IS NOT NULL AND @tipo <> 'varchar',
  "ALTER TABLE `ano_letivo` MODIFY COLUMN `periodo_tipo` VARCHAR(20) NOT NULL DEFAULT 'bimestre' COMMENT 'Divisão do ano: bimestre/trimestre/semestre/etapa_unica'",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'ano_letivo' AND COLUMN_NAME = 'periodo_tipo'
);

SET @sql := IF(
  @has_ano > 0 AND @col > 0,
  "UPDATE `ano_letivo` SET `periodo_tipo` = CASE
      WHEN LOWER(`periodo_tipo`) IN ('bimestral') THEN 'bimestre'
      WHEN LOWER(`periodo_tipo`) IN ('trimestral') THEN 'trimestre'
      WHEN LOWER(`periodo_tipo`) IN ('semestral') THEN 'semestre'
      ELSE `periodo_tipo`
    END",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
