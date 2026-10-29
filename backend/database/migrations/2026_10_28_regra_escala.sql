-- Escala da regra de aprovação: 0 a 10, 0 a 100 ou conceitos (A+, A, B, C...).
-- Tenant. Sem escola_id: o isolamento é o banco. Idempotente.
-- Rollback: 2026_10_28_regra_escala_rollback.sql

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'regras_academicas'
    AND COLUMN_NAME = 'escala'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `regras_academicas`
     ADD COLUMN `escala` VARCHAR(20) NOT NULL DEFAULT ''10''
       COMMENT ''10, 100 ou conceito''
     AFTER `media_minima`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'regras_academicas'
    AND COLUMN_NAME = 'conceito_minimo'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `regras_academicas`
     ADD COLUMN `conceito_minimo` VARCHAR(8) NULL DEFAULT NULL
       COMMENT ''Conceito mínimo para aprovar quando escala=conceito''
     AFTER `escala`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
