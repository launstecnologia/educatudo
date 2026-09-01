-- Rollback de 2026_08_31_reunioes_ata_campos.sql

SET @db := DATABASE();

SET @col_link := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reunioes' AND COLUMN_NAME = 'link_reuniao'
);
SET @sql_link := IF(
  @col_link > 0,
  'ALTER TABLE `reunioes` DROP COLUMN `link_reuniao`',
  'SELECT 1'
);
PREPARE stmt_link_rb FROM @sql_link;
EXECUTE stmt_link_rb;
DEALLOCATE PREPARE stmt_link_rb;

SET @col_encaminhamentos := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reunioes' AND COLUMN_NAME = 'encaminhamentos'
);
SET @sql_encaminhamentos := IF(
  @col_encaminhamentos > 0,
  'ALTER TABLE `reunioes` DROP COLUMN `encaminhamentos`',
  'SELECT 1'
);
PREPARE stmt_enc_rb FROM @sql_encaminhamentos;
EXECUTE stmt_enc_rb;
DEALLOCATE PREPARE stmt_enc_rb;

SET @col_participantes := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reunioes' AND COLUMN_NAME = 'participantes'
);
SET @sql_participantes := IF(
  @col_participantes > 0,
  'ALTER TABLE `reunioes` DROP COLUMN `participantes`',
  'SELECT 1'
);
PREPARE stmt_part_rb FROM @sql_participantes;
EXECUTE stmt_part_rb;
DEALLOCATE PREPARE stmt_part_rb;

SET @col_relator := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'reunioes' AND COLUMN_NAME = 'relator_nome'
);
SET @sql_relator := IF(
  @col_relator > 0,
  'ALTER TABLE `reunioes` DROP COLUMN `relator_nome`',
  'SELECT 1'
);
PREPARE stmt_relator_rb FROM @sql_relator;
EXECUTE stmt_relator_rb;
DEALLOCATE PREPARE stmt_relator_rb;
