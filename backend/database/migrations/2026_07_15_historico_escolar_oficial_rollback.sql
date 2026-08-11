-- Rollback de 2026_07_15_historico_escolar_oficial.sql

DROP TABLE IF EXISTS `historico_assinaturas`;
DROP TABLE IF EXISTS `historico_resultados_anuais`;
DROP TABLE IF EXISTS `historico_itens`;
DROP TABLE IF EXISTS `historico_documentos`;

SET @db := DATABASE();

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'secretario_registro'
);
SET @sql := IF(@has = 0, 'SELECT 1', 'ALTER TABLE unidades DROP COLUMN secretario_registro');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'diretor_registro'
);
SET @sql := IF(@has = 0, 'SELECT 1', 'ALTER TABLE unidades DROP COLUMN diretor_registro');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'ato_reconhecimento'
);
SET @sql := IF(@has = 0, 'SELECT 1', 'ALTER TABLE unidades DROP COLUMN ato_reconhecimento');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'ato_credenciamento'
);
SET @sql := IF(@has = 0, 'SELECT 1', 'ALTER TABLE unidades DROP COLUMN ato_credenciamento');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'ato_autorizacao'
);
SET @sql := IF(@has = 0, 'SELECT 1', 'ALTER TABLE unidades DROP COLUMN ato_autorizacao');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
