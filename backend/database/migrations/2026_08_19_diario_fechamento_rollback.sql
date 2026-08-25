-- Rollback: 2026_08_19_diario_fechamento.sql

SET @db := DATABASE();

-- Reverte diario_frequencias.situacao para o ENUM anterior. Registros que já
-- usem 'saida_antecipada' são convertidos para 'presente' antes de reduzir o
-- ENUM, para não perder a linha nem quebrar o ALTER.
SET @has_frequencias := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_frequencias');
SET @enum_atual := (SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_frequencias' AND COLUMN_NAME='situacao');

SET @sql := IF(@has_frequencias>0 AND @enum_atual LIKE '%saida_antecipada%',
  "UPDATE `diario_frequencias` SET `situacao` = 'presente' WHERE `situacao` = 'saida_antecipada'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has_frequencias>0 AND @enum_atual LIKE '%saida_antecipada%',
  "ALTER TABLE `diario_frequencias` MODIFY COLUMN `situacao` ENUM('presente','falta','falta_justificada','atraso') NOT NULL DEFAULT 'presente'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Remove a tabela de fechamento
SET @has_fechamentos := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_fechamentos');
SET @sql := IF(@has_fechamentos>0, "DROP TABLE `diario_fechamentos`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
