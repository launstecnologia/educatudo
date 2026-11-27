-- Rollback: remove resposta citada em familia_mensagens.
-- Par: 2026_11_26_fale_com_escola_resposta_citada.sql

SET @db := DATABASE();

SET @fk := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'familia_mensagens'
      AND CONSTRAINT_NAME = 'fk_familia_msg_resposta'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@fk > 0,
    'ALTER TABLE `familia_mensagens` DROP FOREIGN KEY `fk_familia_msg_resposta`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'familia_mensagens' AND INDEX_NAME = 'idx_familia_msg_resposta'
);
SET @sql := IF(@idx > 0,
    'ALTER TABLE `familia_mensagens` DROP INDEX `idx_familia_msg_resposta`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'familia_mensagens' AND COLUMN_NAME = 'resposta_a_mensagem_id'
);
SET @sql := IF(@col > 0,
    'ALTER TABLE `familia_mensagens` DROP COLUMN `resposta_a_mensagem_id`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
