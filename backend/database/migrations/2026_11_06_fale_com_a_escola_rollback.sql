-- Desfaz o canal Fale com a Escola e a imagem das respostas.
-- Tenant. As mensagens da família são removidas de propósito neste rollback.

SET @db := DATABASE();

SET @has := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'school_communication_replies' AND COLUMN_NAME = 'imagem'
);
SET @sql := IF(@has = 1,
    'ALTER TABLE `school_communication_replies` DROP COLUMN `imagem_nome`, DROP COLUMN `imagem`',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `familia_mensagens`;
DROP TABLE IF EXISTS `familia_conversas`;
