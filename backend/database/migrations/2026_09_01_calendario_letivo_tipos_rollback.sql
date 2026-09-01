-- Rollback de 2026_09_01_calendario_letivo_tipos.sql
-- Tipos personalizados voltam a 'evento' para caber no ENUM original.
-- DROP da tabela de tipos: dados de cadastro customizado são descartados de propósito
-- (rollback reverte o schema; eventos existentes não podem apontar para slug inexistente).

SET @db := DATABASE();

SET @has_eventos := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo_eventos'
);

SET @sql_fix := IF(
  @has_eventos > 0,
  "UPDATE `calendario_letivo_eventos`
      SET `tipo` = 'evento'
    WHERE `tipo` NOT IN ('feriado','recesso','reposicao','evento','suspensao','avaliacao')",
  'SELECT 1'
);
PREPARE stmt_fix FROM @sql_fix;
EXECUTE stmt_fix;
DEALLOCATE PREPARE stmt_fix;

SET @tipo_data := (
  SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo_eventos' AND COLUMN_NAME = 'tipo'
);
SET @sql_enum := IF(
  @has_eventos > 0 AND @tipo_data = 'varchar',
  "ALTER TABLE `calendario_letivo_eventos`
     MODIFY COLUMN `tipo` ENUM('feriado','recesso','reposicao','evento','suspensao','avaliacao') NOT NULL DEFAULT 'feriado'",
  'SELECT 1'
);
PREPARE stmt_enum FROM @sql_enum;
EXECUTE stmt_enum;
DEALLOCATE PREPARE stmt_enum;

DROP TABLE IF EXISTS `calendario_letivo_tipos`;
