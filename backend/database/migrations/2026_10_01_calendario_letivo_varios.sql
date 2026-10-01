-- Vários calendários letivos no mesmo ano.
-- Cada um pode valer para a escola inteira, para cursos (Fundamental, Ensino Médio)
-- ou para séries específicas. O calendário já existente vira "Geral".
-- Tenant. Idempotente. Rollback: 2026_10_01_calendario_letivo_varios_rollback.sql

SET @db := DATABASE();

SET @has_cal := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo'
);

SET @has_nome := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo' AND COLUMN_NAME = 'nome'
);
SET @sql_nome := IF(
  @has_cal > 0 AND @has_nome = 0,
  "ALTER TABLE `calendario_letivo` ADD COLUMN `nome` VARCHAR(120) NOT NULL DEFAULT 'Geral' AFTER `ano`",
  'SELECT 1'
);
PREPARE stmt_nome FROM @sql_nome;
EXECUTE stmt_nome;
DEALLOCATE PREPARE stmt_nome;

SET @sql_fill := IF(
  @has_cal > 0,
  "UPDATE `calendario_letivo` SET `nome` = 'Geral' WHERE TRIM(`nome`) = ''",
  'SELECT 1'
);
PREPARE stmt_fill FROM @sql_fill;
EXECUTE stmt_fill;
DEALLOCATE PREPARE stmt_fill;

SET @has_uk_ano := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo' AND INDEX_NAME = 'uk_calendario_ano'
);
SET @sql_drop := IF(
  @has_cal > 0 AND @has_uk_ano > 0,
  'ALTER TABLE `calendario_letivo` DROP INDEX `uk_calendario_ano`',
  'SELECT 1'
);
PREPARE stmt_drop FROM @sql_drop;
EXECUTE stmt_drop;
DEALLOCATE PREPARE stmt_drop;

SET @has_uk_nome := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo' AND INDEX_NAME = 'uk_calendario_ano_nome'
);
SET @sql_uk := IF(
  @has_cal > 0 AND @has_uk_nome = 0,
  'ALTER TABLE `calendario_letivo` ADD UNIQUE KEY `uk_calendario_ano_nome` (`ano`, `nome`)',
  'SELECT 1'
);
PREPARE stmt_uk FROM @sql_uk;
EXECUTE stmt_uk;
DEALLOCATE PREPARE stmt_uk;

SET @has_vinc := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo_vinculos'
);
SET @sql_vinc := IF(
  @has_cal > 0 AND @has_vinc = 0,
  "CREATE TABLE `calendario_letivo_vinculos` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `calendario_id` INT(11) NOT NULL,
      `curso_id` INT(11) NULL,
      `serie_id` INT(11) NULL,
      PRIMARY KEY (`id`),
      KEY `idx_cal_vinc_calendario` (`calendario_id`),
      KEY `idx_cal_vinc_curso` (`curso_id`),
      KEY `idx_cal_vinc_serie` (`serie_id`),
      CONSTRAINT `fk_cal_vinc_calendario` FOREIGN KEY (`calendario_id`) REFERENCES `calendario_letivo` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1'
);
PREPARE stmt_vinc FROM @sql_vinc;
EXECUTE stmt_vinc;
DEALLOCATE PREPARE stmt_vinc;
