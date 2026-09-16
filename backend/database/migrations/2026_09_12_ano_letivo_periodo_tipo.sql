-- Divisão do ano letivo (bimestral/trimestral/semestral/etapa única).
-- Fonte única para selects de período em prova, jornada, boletim, conselho etc.
-- Tenant. Idempotente. Rollback: 2026_09_12_ano_letivo_periodo_tipo_rollback.sql

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
  "ALTER TABLE `ano_letivo` ADD COLUMN `periodo_tipo` ENUM('bimestre','trimestre','semestre','etapa_unica') NOT NULL DEFAULT 'bimestre' COMMENT 'Divisão do ano: 4/3/2/1 períodos' AFTER `data_fim`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
