-- Foto de perfil do responsável (app dos pais).

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'responsaveis'
       AND COLUMN_NAME = 'foto_url') = 0,
    'ALTER TABLE `responsaveis` ADD COLUMN `foto_url` VARCHAR(500) NULL DEFAULT NULL COMMENT ''URL da foto de perfil do responsável'' AFTER `telefone`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
