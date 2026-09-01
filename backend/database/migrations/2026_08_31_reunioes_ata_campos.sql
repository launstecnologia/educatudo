-- Campos extras da ata de reunião (geral e com pais).
-- relator_nome, participantes, encaminhamentos, link_reuniao.
-- Rollback: 2026_08_31_reunioes_ata_campos_rollback.sql

SET @db := DATABASE();

SET @col_relator := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reunioes' AND COLUMN_NAME = 'relator_nome'
);
SET @sql_relator := IF(
  @col_relator > 0,
  'SELECT 1',
  "ALTER TABLE `reunioes` ADD COLUMN `relator_nome` VARCHAR(255) NULL DEFAULT NULL AFTER `responsavel_nome`"
);
PREPARE stmt_relator FROM @sql_relator;
EXECUTE stmt_relator;
DEALLOCATE PREPARE stmt_relator;

SET @col_participantes := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reunioes' AND COLUMN_NAME = 'participantes'
);
SET @sql_participantes := IF(
  @col_participantes > 0,
  'SELECT 1',
  "ALTER TABLE `reunioes` ADD COLUMN `participantes` TEXT NULL AFTER `relator_nome`"
);
PREPARE stmt_participantes FROM @sql_participantes;
EXECUTE stmt_participantes;
DEALLOCATE PREPARE stmt_participantes;

SET @col_encaminhamentos := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reunioes' AND COLUMN_NAME = 'encaminhamentos'
);
SET @sql_encaminhamentos := IF(
  @col_encaminhamentos > 0,
  'SELECT 1',
  "ALTER TABLE `reunioes` ADD COLUMN `encaminhamentos` TEXT NULL AFTER `participantes`"
);
PREPARE stmt_encaminhamentos FROM @sql_encaminhamentos;
EXECUTE stmt_encaminhamentos;
DEALLOCATE PREPARE stmt_encaminhamentos;

SET @col_link := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reunioes' AND COLUMN_NAME = 'link_reuniao'
);
SET @sql_link := IF(
  @col_link > 0,
  'SELECT 1',
  "ALTER TABLE `reunioes` ADD COLUMN `link_reuniao` VARCHAR(500) NULL DEFAULT NULL AFTER `local_reuniao`"
);
PREPARE stmt_link FROM @sql_link;
EXECUTE stmt_link;
DEALLOCATE PREPARE stmt_link;
