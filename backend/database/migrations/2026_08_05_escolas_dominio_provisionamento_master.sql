-- MASTER apenas. Status de provisionamento DNS/SSL por escola (wildcard + verificação HTTPS).

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'escolas'
       AND COLUMN_NAME = 'dns_status') = 0,
    'ALTER TABLE `escolas`
        ADD COLUMN `dns_status` ENUM(''nao_configurado'',''wildcard_ok'',''fora_padrao'',''erro'') NOT NULL DEFAULT ''nao_configurado'' COMMENT ''Status DNS inferido (wildcard)'' AFTER `dominio`,
        ADD COLUMN `ssl_status` ENUM(''nao_verificado'',''ok'',''pendente'',''erro'') NOT NULL DEFAULT ''nao_verificado'' COMMENT ''Última verificação HTTPS'' AFTER `dns_status`,
        ADD COLUMN `ssl_verificado_em` DATETIME NULL DEFAULT NULL AFTER `ssl_status`,
        ADD COLUMN `ssl_expira_em` DATETIME NULL DEFAULT NULL AFTER `ssl_verificado_em`,
        ADD COLUMN `dominio_ultimo_erro` TEXT NULL DEFAULT NULL AFTER `ssl_expira_em`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
