-- MASTER apenas. Foto de perfil dos usuários do painel admin master.

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'usuarios_master'
       AND COLUMN_NAME = 'avatar_url') = 0,
    'ALTER TABLE `usuarios_master` ADD COLUMN `avatar_url` VARCHAR(500) NULL DEFAULT NULL COMMENT ''URL da foto de perfil (storage/files/master/avatars/)'' AFTER `nome`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
