-- Rollback: remove reações e colunas de anexo rico do Fale com a Escola.
-- Tenant. Idempotente.

SET @db := DATABASE();

SET @has := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'familia_mensagem_reacoes'
);
SET @sql := IF(@has > 0, 'DROP TABLE `familia_mensagem_reacoes`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'familia_mensagens' AND COLUMN_NAME = 'anexo_mime'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `familia_mensagens` DROP COLUMN `anexo_mime`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'familia_mensagens' AND COLUMN_NAME = 'anexo_tipo'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `familia_mensagens` DROP COLUMN `anexo_tipo`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
