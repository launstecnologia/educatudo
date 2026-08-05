-- Rollback MASTER: remove índice único de dominio.

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'escolas'
       AND INDEX_NAME = 'uk_escolas_dominio') > 0,
    'ALTER TABLE `escolas` DROP INDEX `uk_escolas_dominio`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
