-- ============================================================
-- Financeiro Escolar v2 — Sprint 1-5 completo
-- 2026_07_02_finance_v2.sql
-- ============================================================

-- Configuração financeira por escola
CREATE TABLE IF NOT EXISTS finance_config (
    id                      INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    juros_mensal            DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    multa_atraso            DECIMAL(5,2) NOT NULL DEFAULT 2.00,
    dias_carencia           TINYINT NOT NULL DEFAULT 0,
    dia_vencimento_padrao   TINYINT NOT NULL DEFAULT 10,
    gerar_debito_auto       TINYINT(1) NOT NULL DEFAULT 1,
    email_remetente         VARCHAR(200) NULL,
    nome_escola_boleto      VARCHAR(200) NULL,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO finance_config (id, juros_mensal, multa_atraso, dias_carencia, dia_vencimento_padrao, gerar_debito_auto)
VALUES (1, 1.00, 2.00, 0, 10, 1);

-- Planos pré-configurados
CREATE TABLE IF NOT EXISTS finance_plans (
    id              INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(200) NOT NULL,
    descricao       TEXT NULL,
    ano_letivo_id   INT NOT NULL,
    serie_id        INT NULL,
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ano (ano_letivo_id),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Itens de um plano
CREATE TABLE IF NOT EXISTS finance_plan_items (
    id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    plan_id             INT NOT NULL,
    categoria           ENUM('mensalidade','matricula','material_didatico','uniforme','taxa','outros') NOT NULL,
    descricao           VARCHAR(200) NOT NULL,
    valor_base          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    num_parcelas        INT NOT NULL DEFAULT 1,
    mes_inicio          TINYINT NOT NULL DEFAULT 1,
    mes_fim             TINYINT NULL,
    dia_vencimento      TINYINT NULL,
    fornecedor_externo  TINYINT(1) NOT NULL DEFAULT 0,
    nome_instituicao    VARCHAR(200) NULL,
    ordem               INT NOT NULL DEFAULT 0,
    INDEX idx_plan (plan_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Itens de um contrato (um por categoria/lote)
CREATE TABLE IF NOT EXISTS finance_contract_items (
    id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contract_id         INT NOT NULL,
    plan_item_id        INT NULL,
    price_table_id      INT NULL,
    categoria           ENUM('mensalidade','matricula','material_didatico','uniforme','taxa','outros') NOT NULL,
    descricao           VARCHAR(200) NOT NULL,
    valor_unitario      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quantidade          DECIMAL(10,3) NOT NULL DEFAULT 1.000,
    valor_total         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_desconto      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_liquido       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    num_parcelas        INT NOT NULL DEFAULT 1,
    mes_inicio          TINYINT NOT NULL DEFAULT 1,
    mes_fim             TINYINT NULL,
    dia_vencimento      TINYINT NULL,
    fornecedor_externo  TINYINT(1) NOT NULL DEFAULT 0,
    nome_instituicao    VARCHAR(200) NULL,
    status              ENUM('ativo','cancelado') NOT NULL DEFAULT 'ativo',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cobranças avulsas (sem contrato)
CREATE TABLE IF NOT EXISTS finance_charges (
    id              INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    aluno_id        INT NOT NULL,
    ano_letivo_id   INT NULL,
    categoria       ENUM('mensalidade','matricula','material_didatico','uniforme','taxa','outros') NOT NULL DEFAULT 'outros',
    descricao       VARCHAR(200) NOT NULL,
    valor           DECIMAL(10,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento  DATE NULL,
    valor_pago      DECIMAL(10,2) NULL,
    forma_pagamento ENUM('dinheiro','pix','boleto','transferencia','cartao','outro') NULL,
    juros_aplicado  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    multa_aplicada  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status          ENUM('pendente','pago','vencido','cancelado') NOT NULL DEFAULT 'pendente',
    boleto_codigo   VARCHAR(100) NULL,
    observacoes     TEXT NULL,
    criado_por      INT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_aluno (aluno_id),
    INDEX idx_status (status),
    INDEX idx_vencimento (data_vencimento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Extrato financeiro do aluno (estilo bancário)
CREATE TABLE IF NOT EXISTS finance_ledger (
    id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    aluno_id            INT NOT NULL,
    tipo                ENUM('debito','credito','estorno','ajuste') NOT NULL,
    categoria           VARCHAR(50) NOT NULL DEFAULT 'outros',
    descricao           VARCHAR(200) NOT NULL,
    valor               DECIMAL(10,2) NOT NULL,
    saldo_acumulado     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    data_lancamento     DATE NOT NULL,
    referencia_tipo     ENUM('installment','charge','payment','estorno','ajuste','manual') NULL,
    referencia_id       INT NULL,
    contract_id         INT NULL,
    observacoes         TEXT NULL,
    gerado_auto         TINYINT(1) NOT NULL DEFAULT 0,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_aluno (aluno_id),
    INDEX idx_data (data_lancamento),
    INDEX idx_ref (referencia_tipo, referencia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Renegociações de dívida
CREATE TABLE IF NOT EXISTS finance_renegotiations (
    id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    aluno_id            INT NOT NULL,
    contract_id         INT NOT NULL,
    valor_total_divida  DECIMAL(10,2) NOT NULL,
    valor_entrada       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_parcelado     DECIMAL(10,2) NOT NULL,
    num_parcelas        INT NOT NULL DEFAULT 1,
    dia_vencimento      TINYINT NOT NULL DEFAULT 10,
    mes_inicio          TINYINT NOT NULL,
    ano_inicio          INT NOT NULL,
    status              ENUM('ativo','quitado','cancelado') NOT NULL DEFAULT 'ativo',
    observacoes         TEXT NULL,
    criado_por          INT NULL,
    aprovado_por        INT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_aluno (aluno_id),
    INDEX idx_contract (contract_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recibos gerados
CREATE TABLE IF NOT EXISTS finance_receipts (
    id              INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    payment_id      INT NULL,
    charge_id       INT NULL,
    aluno_id        INT NOT NULL,
    valor           DECIMAL(10,2) NOT NULL,
    data_pagamento  DATE NOT NULL,
    numero          VARCHAR(20) NOT NULL,
    pdf_path        VARCHAR(500) NULL,
    enviado_email   TINYINT(1) NOT NULL DEFAULT 0,
    enviado_wpp     TINYINT(1) NOT NULL DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_numero (numero),
    INDEX idx_aluno (aluno_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alterações em tabelas existentes (usa IF NOT EXISTS via information_schema para compatibilidade com MySQL 8)
SET @sql = (SELECT IF(
    NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_contracts' AND COLUMN_NAME = 'plan_id'
    ),
    'ALTER TABLE finance_contracts ADD COLUMN plan_id INT NULL AFTER enrollment_id',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'finance_installments' AND COLUMN_NAME = 'contract_item_id'
    ),
    'ALTER TABLE finance_installments ADD COLUMN contract_item_id INT NULL AFTER contract_id',
    'SELECT 1'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
