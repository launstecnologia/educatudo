-- Desfaz 2026_10_26_eventos_avaliativos.sql
-- Tenant. Idempotente. Não apaga provas_blocos nem notas já lançadas.

SET @db := DATABASE();

SET @fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_provas_blocos_evento_avaliativo'
);
SET @sql := IF(
  @fk > 0,
  'ALTER TABLE `provas_blocos` DROP FOREIGN KEY `fk_provas_blocos_evento_avaliativo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND INDEX_NAME = 'idx_provas_blocos_evento_avaliativo'
);
SET @sql := IF(
  @idx > 0,
  'ALTER TABLE `provas_blocos` DROP INDEX `idx_provas_blocos_evento_avaliativo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'evento_avaliativo_id'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `provas_blocos` DROP COLUMN `evento_avaliativo_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `eventos_avaliativos`;
