-- Rollback de 2026_10_28_boletim_layouts_varios.sql
-- Tira nome, finalidade e o público do boletim. O layout_json de cada linha fica.
-- A chave única por ano só volta quando cada ano tem uma linha.

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'turmas_ids'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `boletim_layouts` DROP COLUMN `turmas_ids`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'series_ids'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `boletim_layouts` DROP COLUMN `series_ids`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'materias_ids'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `boletim_layouts` DROP COLUMN `materias_ids`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'regra_academica_id'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `boletim_layouts` DROP COLUMN `regra_academica_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'ativo'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `boletim_layouts` DROP COLUMN `ativo`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'vis_pais'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `boletim_layouts` DROP COLUMN `vis_pais`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'vis_aluno'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `boletim_layouts` DROP COLUMN `vis_aluno`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'finalidade'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `boletim_layouts` DROP COLUMN `finalidade`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'nome'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `boletim_layouts` DROP COLUMN `nome`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND INDEX_NAME = 'idx_boletim_layouts_ano'
);
SET @sql := IF(@idx > 0, 'ALTER TABLE `boletim_layouts` DROP INDEX `idx_boletim_layouts_ano`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @dup := (
  SELECT COUNT(*) FROM (
    SELECT `ano_letivo` FROM `boletim_layouts` GROUP BY `ano_letivo` HAVING COUNT(*) > 1
  ) AS repetidos
);
SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND INDEX_NAME = 'uk_boletim_layouts_ano'
);
SET @sql := IF(
  @dup = 0 AND @idx = 0,
  'ALTER TABLE `boletim_layouts` ADD UNIQUE KEY `uk_boletim_layouts_ano` (`ano_letivo`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
