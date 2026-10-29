-- Vários boletins no mesmo ano: oficial da vida escolar ou extra.
-- O layout_json continua na mesma tabela. O demonstrativo de notas não usa estas colunas.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_28_boletim_layouts_varios_rollback.sql

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'nome'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `boletim_layouts` ADD COLUMN `nome` VARCHAR(180) NULL DEFAULT NULL AFTER `ano_letivo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'finalidade'
);
SET @sql := IF(
  @col = 0,
  "ALTER TABLE `boletim_layouts` ADD COLUMN `finalidade` ENUM('oficial','complementar') NOT NULL DEFAULT 'oficial' AFTER `nome`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'vis_aluno'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `boletim_layouts` ADD COLUMN `vis_aluno` TINYINT(1) NOT NULL DEFAULT 1 AFTER `finalidade`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'vis_pais'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `boletim_layouts` ADD COLUMN `vis_pais` TINYINT(1) NOT NULL DEFAULT 1 AFTER `vis_aluno`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'ativo'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `boletim_layouts` ADD COLUMN `ativo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `vis_pais`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'regra_academica_id'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `boletim_layouts` ADD COLUMN `regra_academica_id` INT UNSIGNED NULL DEFAULT NULL AFTER `ativo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'materias_ids'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `boletim_layouts` ADD COLUMN `materias_ids` TEXT NULL DEFAULT NULL AFTER `regra_academica_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'series_ids'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `boletim_layouts` ADD COLUMN `series_ids` TEXT NULL DEFAULT NULL AFTER `materias_ids`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND COLUMN_NAME = 'turmas_ids'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `boletim_layouts` ADD COLUMN `turmas_ids` TEXT NULL DEFAULT NULL AFTER `series_ids`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `boletim_layouts`
   SET `nome` = 'Boletim oficial'
 WHERE `nome` IS NULL OR `nome` = '';

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND INDEX_NAME = 'uk_boletim_layouts_ano'
);
SET @sql := IF(@idx > 0, 'ALTER TABLE `boletim_layouts` DROP INDEX `uk_boletim_layouts_ano`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_layouts' AND INDEX_NAME = 'idx_boletim_layouts_ano'
);
SET @sql := IF(@idx = 0, 'ALTER TABLE `boletim_layouts` ADD KEY `idx_boletim_layouts_ano` (`ano_letivo`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
