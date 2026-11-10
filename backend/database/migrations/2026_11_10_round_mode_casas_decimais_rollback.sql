-- Rollback de 2026_11_10_round_mode_casas_decimais.sql
-- Valores 'places' voltam para 'none' antes de encolher o ENUM.

SET @db := DATABASE();

UPDATE `regras_academicas`
SET `round_mode` = 'none'
WHERE `round_mode` = 'places';

UPDATE `boletim_regras`
SET `round_mode` = 'none'
WHERE `round_mode` = 'places';

SET @tem_regras := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND COLUMN_NAME = 'round_mode'
    AND COLUMN_TYPE LIKE '%places%'
);
SET @sql := IF(@tem_regras > 0,
  "ALTER TABLE `regras_academicas`
   MODIFY COLUMN `round_mode` ENUM('none','half') NOT NULL DEFAULT 'none'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_boletim := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_regras' AND COLUMN_NAME = 'round_mode'
    AND COLUMN_TYPE LIKE '%places%'
);
SET @sql := IF(@tem_boletim > 0,
  "ALTER TABLE `boletim_regras`
   MODIFY COLUMN `round_mode` ENUM('none','half') NOT NULL DEFAULT 'none'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
