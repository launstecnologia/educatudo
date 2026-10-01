-- Desfaz vários calendários letivos por ano.
-- Mantém só o calendário mais antigo de cada ano (os demais e os eventos deles saem).
-- Tenant.

SET @db := DATABASE();

SET @has_vinc := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo_vinculos'
);
SET @sql_drop_vinc := IF(
  @has_vinc > 0,
  'DROP TABLE `calendario_letivo_vinculos`',
  'SELECT 1'
);
PREPARE stmt_drop_vinc FROM @sql_drop_vinc;
EXECUTE stmt_drop_vinc;
DEALLOCATE PREPARE stmt_drop_vinc;

SET @has_cal := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo'
);

SET @sql_extras := IF(
  @has_cal > 0,
  "DELETE c FROM `calendario_letivo` c
   INNER JOIN (
       SELECT `ano`, MIN(`id`) AS `id` FROM `calendario_letivo` GROUP BY `ano`
   ) g ON g.ano = c.ano AND c.id <> g.id",
  'SELECT 1'
);
PREPARE stmt_extras FROM @sql_extras;
EXECUTE stmt_extras;
DEALLOCATE PREPARE stmt_extras;

SET @has_uk_nome := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo' AND INDEX_NAME = 'uk_calendario_ano_nome'
);
SET @sql_drop_uk := IF(
  @has_cal > 0 AND @has_uk_nome > 0,
  'ALTER TABLE `calendario_letivo` DROP INDEX `uk_calendario_ano_nome`',
  'SELECT 1'
);
PREPARE stmt_drop_uk FROM @sql_drop_uk;
EXECUTE stmt_drop_uk;
DEALLOCATE PREPARE stmt_drop_uk;

SET @has_nome := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo' AND COLUMN_NAME = 'nome'
);
SET @sql_drop_nome := IF(
  @has_cal > 0 AND @has_nome > 0,
  'ALTER TABLE `calendario_letivo` DROP COLUMN `nome`',
  'SELECT 1'
);
PREPARE stmt_drop_nome FROM @sql_drop_nome;
EXECUTE stmt_drop_nome;
DEALLOCATE PREPARE stmt_drop_nome;

SET @has_uk_ano := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo' AND INDEX_NAME = 'uk_calendario_ano'
);
SET @sql_uk := IF(
  @has_cal > 0 AND @has_uk_ano = 0,
  'ALTER TABLE `calendario_letivo` ADD UNIQUE KEY `uk_calendario_ano` (`ano`)',
  'SELECT 1'
);
PREPARE stmt_uk FROM @sql_uk;
EXECUTE stmt_uk;
DEALLOCATE PREPARE stmt_uk;
