-- Rollback MASTER: remove colunas de provisionamento DNS/SSL de escolas.

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'escolas'
       AND COLUMN_NAME = 'dominio_ultimo_erro') > 0,
    'ALTER TABLE `escolas`
        DROP COLUMN `dominio_ultimo_erro`,
        DROP COLUMN `ssl_expira_em`,
        DROP COLUMN `ssl_verificado_em`,
        DROP COLUMN `ssl_status`,
        DROP COLUMN `dns_status`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
