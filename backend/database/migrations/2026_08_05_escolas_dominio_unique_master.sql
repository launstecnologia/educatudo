-- MASTER apenas. Garante domínio único por escola (roteamento multi-tenant por HTTP_HOST).

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'escolas'
       AND INDEX_NAME = 'uk_escolas_dominio') = 0,
    'ALTER TABLE `escolas` ADD UNIQUE KEY `uk_escolas_dominio` (`dominio`)',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
