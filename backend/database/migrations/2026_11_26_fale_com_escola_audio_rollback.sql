-- Rollback: remove 'audio' do ENUM anexo_tipo (áudios passam a NULL).
-- Tenant. Idempotente.

SET @db := DATABASE();

UPDATE `familia_mensagens` SET `anexo_tipo` = NULL WHERE `anexo_tipo` = 'audio';

SET @tipo := (
    SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'familia_mensagens' AND COLUMN_NAME = 'anexo_tipo'
);
SET @sql := IF(
    @tipo IS NOT NULL AND @tipo LIKE '%audio%',
    "ALTER TABLE `familia_mensagens`
        MODIFY COLUMN `anexo_tipo` ENUM('imagem','video','arquivo') NULL DEFAULT NULL
        COMMENT 'Tipo do anexo; imagem legado também usa coluna imagem'",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
