-- Desfaz 2026_11_01_leitor_gabarito_folha_conjunto.sql
-- Apaga as folhas e leituras do gabarito do bloco. As folhas de uma matéria ficam.

SET @col_leitura := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'leitor_gabarito_leituras'
    AND COLUMN_NAME = 'conjunto'
);
SET @sql := IF(
  @col_leitura > 0,
  'DELETE FROM `leitor_gabarito_leituras` WHERE `conjunto` = 1',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_folha := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'leitor_gabarito_folhas'
    AND COLUMN_NAME = 'conjunto'
);
SET @sql := IF(
  @col_folha > 0,
  'DELETE FROM `leitor_gabarito_folhas` WHERE `conjunto` = 1',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_folha_novo := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'leitor_gabarito_folhas'
    AND INDEX_NAME = 'uk_leitor_folha_prova_aluno_conjunto'
);
SET @sql := IF(
  @idx_folha_novo > 0,
  'ALTER TABLE `leitor_gabarito_folhas` DROP INDEX `uk_leitor_folha_prova_aluno_conjunto`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @col_folha > 0,
  'ALTER TABLE `leitor_gabarito_folhas` DROP COLUMN `conjunto`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_folha_antigo := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'leitor_gabarito_folhas'
    AND INDEX_NAME = 'uk_leitor_folha_prova_aluno'
);
SET @sql := IF(
  @idx_folha_antigo = 0,
  'ALTER TABLE `leitor_gabarito_folhas` ADD UNIQUE KEY `uk_leitor_folha_prova_aluno` (`prova_id`, `aluno_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @col_leitura > 0,
  'ALTER TABLE `leitor_gabarito_leituras` DROP COLUMN `conjunto`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
