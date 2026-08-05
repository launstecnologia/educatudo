-- Tenant: carteira da escola (TudiCoins) + ENUM user_type inclui 'escola'.
-- user_type=escola / user_id=1 = saldo institucional (EducaInclui, pool, compras da escola).

ALTER TABLE `carteira_usuarios`
  MODIFY COLUMN `user_type` ENUM('aluno','professor','escola') NOT NULL;

ALTER TABLE `carteira_movimentacoes`
  MODIFY COLUMN `user_type` ENUM('aluno','professor','escola') NOT NULL;

-- Garante colunas de saldo split (podem já existir via 055 / runtime).
SET @db := DATABASE();
SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'carteira_usuarios' AND COLUMN_NAME = 'saldo_escola'
    ),
    'SELECT 1',
    'ALTER TABLE `carteira_usuarios` ADD COLUMN `saldo_escola` DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER `saldo`'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'carteira_usuarios' AND COLUMN_NAME = 'saldo_comprado'
    ),
    'SELECT 1',
    'ALTER TABLE `carteira_usuarios` ADD COLUMN `saldo_comprado` DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER `saldo_escola`'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO `carteira_usuarios` (`user_type`, `user_id`, `saldo`, `saldo_escola`, `saldo_comprado`)
SELECT 'escola', 1, 0, 0, 0
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `carteira_usuarios` WHERE `user_type` = 'escola' AND `user_id` = 1
);
