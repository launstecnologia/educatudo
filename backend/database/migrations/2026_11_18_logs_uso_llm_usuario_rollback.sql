-- Rollback: remove o usuário gravado em logs_uso_llm.

SET @db := DATABASE();

SET @tem_nome := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'logs_uso_llm' AND COLUMN_NAME = 'usuario_nome'
);
SET @sql := IF(@tem_nome > 0,
  'ALTER TABLE `logs_uso_llm` DROP COLUMN `usuario_nome`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'logs_uso_llm' AND COLUMN_NAME = 'usuario_id'
);
SET @sql := IF(@tem_id > 0,
  'ALTER TABLE `logs_uso_llm` DROP COLUMN `usuario_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_tipo := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'logs_uso_llm' AND COLUMN_NAME = 'usuario_tipo'
);
SET @sql := IF(@tem_tipo > 0,
  'ALTER TABLE `logs_uso_llm` DROP COLUMN `usuario_tipo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
