-- Separa a folha de uma matéria da folha do bloco inteiro.
-- Roda depois de 2026_11_01_leitor_gabarito.sql (o nome ordena após o CREATE).
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_11_01_leitor_gabarito_folha_conjunto_rollback.sql

SET @col_folha := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'leitor_gabarito_folhas'
    AND COLUMN_NAME = 'conjunto'
);
SET @sql := IF(
  @col_folha = 0,
  'ALTER TABLE `leitor_gabarito_folhas` ADD COLUMN `conjunto` tinyint NOT NULL DEFAULT 0 COMMENT ''0 = uma matéria, 1 = gabarito do bloco'' AFTER `aluno_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- O índice antigo (prova_id, aluno_id) impede duas folhas do mesmo aluno: uma da matéria e outra do bloco.
SET @idx_folha_antigo := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'leitor_gabarito_folhas'
    AND INDEX_NAME = 'uk_leitor_folha_prova_aluno'
);
SET @sql := IF(
  @idx_folha_antigo > 0,
  'ALTER TABLE `leitor_gabarito_folhas` DROP INDEX `uk_leitor_folha_prova_aluno`',
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
  @idx_folha_novo = 0,
  'ALTER TABLE `leitor_gabarito_folhas` ADD UNIQUE KEY `uk_leitor_folha_prova_aluno_conjunto` (`prova_id`, `aluno_id`, `conjunto`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_leitura := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'leitor_gabarito_leituras'
    AND COLUMN_NAME = 'conjunto'
);
SET @sql := IF(
  @col_leitura = 0,
  'ALTER TABLE `leitor_gabarito_leituras` ADD COLUMN `conjunto` tinyint NOT NULL DEFAULT 0 COMMENT ''0 = uma matéria, 1 = gabarito do bloco'' AFTER `prova_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
