-- Rollback: remove coluna avatar_url de usuarios_master (MASTER).

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'usuarios_master'
       AND COLUMN_NAME = 'avatar_url') > 0,
    'ALTER TABLE `usuarios_master` DROP COLUMN `avatar_url`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
