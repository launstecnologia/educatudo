-- Rollback de 2026_10_29_conceito_minimo_tamanho.sql
-- Volta o conceito mínimo para VARCHAR(8). Valor maior que 8 caracteres é cortado.

SET @db := DATABASE();

SET @len := (
  SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'regras_academicas'
    AND COLUMN_NAME = 'conceito_minimo'
);
SET @sql := IF(
  @len IS NOT NULL AND @len > 8,
  'ALTER TABLE `regras_academicas`
     MODIFY COLUMN `conceito_minimo` VARCHAR(8) NULL DEFAULT NULL
       COMMENT ''Conceito mínimo para aprovar quando escala=conceito''',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
