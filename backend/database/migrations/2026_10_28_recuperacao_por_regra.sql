-- Cada recuperação passa a pertencer a uma regra de aprovação.
-- O que já estava só no ano fica na primeira regra daquele ano.
-- As outras regras do mesmo ano começam sem recuperação.
-- Tenant. Sem escola_id: o isolamento é o banco. Idempotente.
-- Rollback: 2026_10_28_recuperacao_por_regra_rollback.sql

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'recuperacoes'
    AND COLUMN_NAME = 'regra_id'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `recuperacoes`
     ADD COLUMN `regra_id` INT UNSIGNED NULL DEFAULT NULL
       COMMENT ''Regra de aprovação dona desta recuperação''
     AFTER `ano_letivo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'recuperacoes'
    AND INDEX_NAME = 'idx_recuperacoes_regra'
);
SET @sql := IF(
  @idx = 0,
  'ALTER TABLE `recuperacoes` ADD KEY `idx_recuperacoes_regra` (`regra_id`, `deleted_at`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `recuperacoes` AS rec
 INNER JOIN (
   SELECT ano_letivo, MIN(id) AS regra_id
     FROM regras_academicas
    WHERE ano_letivo IS NOT NULL
    GROUP BY ano_letivo
 ) AS primeira
    ON primeira.ano_letivo = rec.ano_letivo
   SET rec.regra_id = primeira.regra_id
 WHERE rec.regra_id IS NULL
   AND rec.deleted_at IS NULL;
