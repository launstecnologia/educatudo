-- Rollback MASTER: remove endereço e contato da instituição em escolas.

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'escolas'
       AND COLUMN_NAME = 'rua') > 0,
    'ALTER TABLE `escolas`
        DROP COLUMN `email`,
        DROP COLUMN `contato_nome`,
        DROP COLUMN `telefone`,
        DROP COLUMN `uf`,
        DROP COLUMN `cidade`,
        DROP COLUMN `bairro`,
        DROP COLUMN `complemento`,
        DROP COLUMN `numero`,
        DROP COLUMN `rua`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
