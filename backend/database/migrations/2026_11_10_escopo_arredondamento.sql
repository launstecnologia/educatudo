-- Escopo do arredondamento na regra de aprovação: só médias/cálculos ou todas as notas.
-- Tenant. Idempotente. Rollback: 2026_11_10_escopo_arredondamento_rollback.sql

SET @db := DATABASE();

SET @tem := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND COLUMN_NAME = 'escopo_arredondamento'
);
SET @sql := IF(@tem = 0,
  "ALTER TABLE `regras_academicas`
   ADD COLUMN `escopo_arredondamento` ENUM('medias','todas') NOT NULL DEFAULT 'medias'
   COMMENT 'medias = só médias/resultados calculados; todas = todas as notas'
   AFTER `decimal_places`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
