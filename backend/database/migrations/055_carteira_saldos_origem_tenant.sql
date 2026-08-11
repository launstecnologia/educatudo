-- TENANT: separa créditos da escola e créditos comprados.
-- Mantém coluna saldo como total agregado para compatibilidade com consultas legadas.
-- Idempotente e compatível com MySQL sem suporte a "ADD COLUMN IF NOT EXISTS".

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'carteira_usuarios'
          AND COLUMN_NAME = 'saldo_escola'
    ),
    'SELECT 1',
    'ALTER TABLE `carteira_usuarios` ADD COLUMN `saldo_escola` DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER `saldo`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'carteira_usuarios'
          AND COLUMN_NAME = 'saldo_comprado'
    ),
    'SELECT 1',
    'ALTER TABLE `carteira_usuarios` ADD COLUMN `saldo_comprado` DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER `saldo_escola`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `carteira_usuarios`
SET `saldo_escola` = COALESCE(`saldo`, 0)
WHERE COALESCE(`saldo_escola`, 0) = 0
  AND COALESCE(`saldo`, 0) > 0;

UPDATE `carteira_usuarios`
SET `saldo_comprado` = 0
WHERE `saldo_comprado` IS NULL;

SET @sql := IF(
    EXISTS(
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'carteira_movimentacoes'
          AND COLUMN_NAME = 'saldo_origem'
    ),
    'SELECT 1',
    'ALTER TABLE `carteira_movimentacoes` ADD COLUMN `saldo_origem` ENUM(''escola'',''comprado'',''misto'') NULL DEFAULT NULL AFTER `tipo`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `carteira_movimentacoes`
SET `saldo_origem` = CASE
    WHEN `tipo` IN ('compra', 'recarga_plano') THEN 'comprado'
    WHEN `tipo` IN ('recarga_mensal', 'recarga_inicial', 'cortesia') THEN 'escola'
    WHEN `tipo` IN ('consumo', 'estorno') THEN 'misto'
    ELSE NULL
END
WHERE `saldo_origem` IS NULL;
