-- MASTER: flag padrao em tabela de preço TudiCoins (uma por vez; escolas novas herdam).

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'creditos_tabela_custo'
       AND COLUMN_NAME = 'padrao') = 0,
    'ALTER TABLE `creditos_tabela_custo` ADD COLUMN `padrao` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = vinculada automaticamente em escolas novas'' AFTER `ativo`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
