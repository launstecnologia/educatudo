-- Fale com a Escola: inclui tipo de anexo áudio.
-- Tenant. Idempotente. Rollback: 2026_11_26_fale_com_escola_audio_rollback.sql

SET @db := DATABASE();

SET @tipo := (
    SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'familia_mensagens' AND COLUMN_NAME = 'anexo_tipo'
);
SET @sql := IF(
    @tipo IS NOT NULL AND @tipo NOT LIKE '%audio%',
    "ALTER TABLE `familia_mensagens`
        MODIFY COLUMN `anexo_tipo` ENUM('imagem','video','arquivo','audio') NULL DEFAULT NULL
        COMMENT 'Tipo do anexo; imagem legado também usa coluna imagem'",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
