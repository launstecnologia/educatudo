-- Rollback: remove foto_url de responsaveis.

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'responsaveis'
       AND COLUMN_NAME = 'foto_url') > 0,
    'ALTER TABLE `responsaveis` DROP COLUMN `foto_url`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
