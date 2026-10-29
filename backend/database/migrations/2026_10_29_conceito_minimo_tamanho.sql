-- Conceito mínimo da escala descritiva (Excelente) não cabe em VARCHAR(8).
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_29_conceito_minimo_tamanho_rollback.sql

SET @db := DATABASE();

SET @len := (
  SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'regras_academicas'
    AND COLUMN_NAME = 'conceito_minimo'
);
SET @sql := IF(
  @len IS NOT NULL AND @len < 32,
  'ALTER TABLE `regras_academicas`
     MODIFY COLUMN `conceito_minimo` VARCHAR(32) NULL DEFAULT NULL
       COMMENT ''Conceito mínimo para aprovar quando a escala é conceitual''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
