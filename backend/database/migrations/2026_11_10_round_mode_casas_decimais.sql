-- Nova opção de arredondamento: conforme casas decimais (ex.: 7,95 → 8,0 com 1 casa).
-- Tenant. Idempotente. Rollback: 2026_11_10_round_mode_casas_decimais_rollback.sql

SET @db := DATABASE();

SET @tem_regras := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND COLUMN_NAME = 'round_mode'
    AND COLUMN_TYPE NOT LIKE '%places%'
);
SET @sql := IF(@tem_regras > 0,
  "ALTER TABLE `regras_academicas`
   MODIFY COLUMN `round_mode` ENUM('none','half','places') NOT NULL DEFAULT 'none'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_boletim := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_regras' AND COLUMN_NAME = 'round_mode'
    AND COLUMN_TYPE NOT LIKE '%places%'
);
SET @sql := IF(@tem_boletim > 0,
  "ALTER TABLE `boletim_regras`
   MODIFY COLUMN `round_mode` ENUM('none','half','places') NOT NULL DEFAULT 'none'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
