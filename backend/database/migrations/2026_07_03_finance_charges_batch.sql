-- Migração: expande finance_charges para suportar cobranças em lote
-- Adiciona unidade_id (empresa emissora NF) e novos tipos de categoria

-- 1. Adiciona unidade_id se não existir
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_charges' AND COLUMN_NAME = 'unidade_id') = 0,
    'ALTER TABLE finance_charges ADD COLUMN unidade_id INT NULL COMMENT "Empresa emissora da NF" AFTER observacoes',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. Adiciona batch_id para agrupar cobranças do mesmo lote
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_charges' AND COLUMN_NAME = 'batch_id') = 0,
    'ALTER TABLE finance_charges ADD COLUMN batch_id VARCHAR(36) NULL AFTER unidade_id',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. Expande ENUM categoria para incluir passeio e ingresso
ALTER TABLE finance_charges
    MODIFY COLUMN categoria ENUM(
        'mensalidade','matricula','material_didatico','uniforme',
        'taxa','passeio','ingresso','evento','outros'
    ) NOT NULL DEFAULT 'outros';

-- 4. Cria índice no batch_id para consultas de lote
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_charges' AND INDEX_NAME = 'idx_batch_id') = 0,
    'ALTER TABLE finance_charges ADD INDEX idx_batch_id (batch_id)',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
