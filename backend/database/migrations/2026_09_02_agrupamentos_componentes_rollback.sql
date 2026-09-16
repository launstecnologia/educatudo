-- Rollback de 2026_09_02_agrupamentos_componentes.sql
-- DROP das tabelas criadas por essa migration (itens primeiro por causa da FK).

SET @db := DATABASE();

SET @has_itens := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'agrupamentos_componentes_itens'
);
SET @sql := IF(@has_itens > 0, 'DROP TABLE `agrupamentos_componentes_itens`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_agrup := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'agrupamentos_componentes'
);
SET @sql := IF(@has_agrup > 0, 'DROP TABLE `agrupamentos_componentes`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
