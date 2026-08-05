-- M4: Novas colunas em turmas (ano_letivo_id, serie_id). Sem FK no primeiro deploy.
-- Colunas antigas (ano_letivo, serie) não são removidas.
-- FKs em turmas só após backfill (027_turmas_fk_ano_serie.sql).

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'turmas'
    AND COLUMN_NAME = 'ano_letivo_id'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `turmas` ADD COLUMN `ano_letivo_id` int(11) DEFAULT NULL AFTER `ano_letivo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'turmas'
    AND COLUMN_NAME = 'serie_id'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `turmas` ADD COLUMN `serie_id` int(11) DEFAULT NULL AFTER `serie`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
