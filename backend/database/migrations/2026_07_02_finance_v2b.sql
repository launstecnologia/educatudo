-- Migration: 2026_07_02_finance_v2b
-- Adiciona unidade_id em finance_plan_items e finance_contract_items
-- Adiciona desconto_pontualidade_pct e desconto_pontualidade_dia em finance_config

-- finance_plan_items: unidade_id
SET @sql = (SELECT IF(
    NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'finance_plan_items'
          AND COLUMN_NAME = 'unidade_id'
    ),
    'ALTER TABLE finance_plan_items ADD COLUMN unidade_id INT NULL AFTER nome_instituicao',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- finance_contract_items: unidade_id
SET @sql = (SELECT IF(
    NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'finance_contract_items'
          AND COLUMN_NAME = 'unidade_id'
    ),
    'ALTER TABLE finance_contract_items ADD COLUMN unidade_id INT NULL AFTER nome_instituicao',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- finance_config: desconto_pontualidade_pct
SET @sql = (SELECT IF(
    NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'finance_config'
          AND COLUMN_NAME = 'desconto_pontualidade_pct'
    ),
    'ALTER TABLE finance_config ADD COLUMN desconto_pontualidade_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- finance_config: desconto_pontualidade_dia
SET @sql = (SELECT IF(
    NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'finance_config'
          AND COLUMN_NAME = 'desconto_pontualidade_dia'
    ),
    'ALTER TABLE finance_config ADD COLUMN desconto_pontualidade_dia TINYINT NOT NULL DEFAULT 5',
    'SELECT 1'
));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
