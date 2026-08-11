-- Rollback: remove coluna padrao de creditos_tabela_custo (MASTER).

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'creditos_tabela_custo'
       AND COLUMN_NAME = 'padrao') > 0,
    'ALTER TABLE `creditos_tabela_custo` DROP COLUMN `padrao`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
