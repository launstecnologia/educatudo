-- Migration: 2026_07_03_finance_bills_plano_contas
-- Contas a Pagar + Plano de Contas simplificado

-- Plano de contas (categorias contábeis)
CREATE TABLE IF NOT EXISTS finance_chart_accounts (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    codigo        VARCHAR(20) NOT NULL,
    nome          VARCHAR(120) NOT NULL,
    tipo          ENUM('receita','despesa','ativo','passivo','patrimonio') NOT NULL,
    grupo         VARCHAR(80)  NULL COMMENT 'Agrupamento: ex. Receitas Operacionais, Despesas Administrativas',
    descricao     TEXT         NULL,
    ativo         TINYINT(1)   NOT NULL DEFAULT 1,
    ordem         INT          NOT NULL DEFAULT 0,
    criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: plano de contas básico escolar
INSERT IGNORE INTO finance_chart_accounts (codigo, nome, tipo, grupo, ordem) VALUES
-- Receitas
('1.1.01', 'Mensalidades',              'receita', 'Receitas Operacionais', 10),
('1.1.02', 'Matrículas',               'receita', 'Receitas Operacionais', 20),
('1.1.03', 'Material Didático',        'receita', 'Receitas Operacionais', 30),
('1.1.04', 'Passeios e Eventos',       'receita', 'Receitas Operacionais', 40),
('1.1.05', 'Uniformes',                'receita', 'Receitas Operacionais', 50),
('1.1.06', 'Outros Serviços',          'receita', 'Receitas Operacionais', 60),
('1.2.01', 'Receitas Financeiras',     'receita', 'Receitas Não Operacionais', 70),
-- Despesas
('2.1.01', 'Folha de Pagamento',       'despesa', 'Despesas com Pessoal', 100),
('2.1.02', 'Encargos Trabalhistas',    'despesa', 'Despesas com Pessoal', 110),
('2.1.03', 'Pró-labore',              'despesa', 'Despesas com Pessoal', 120),
('2.2.01', 'Aluguel',                 'despesa', 'Despesas Operacionais', 200),
('2.2.02', 'Energia Elétrica',        'despesa', 'Despesas Operacionais', 210),
('2.2.03', 'Água e Esgoto',           'despesa', 'Despesas Operacionais', 220),
('2.2.04', 'Internet e Telefonia',    'despesa', 'Despesas Operacionais', 230),
('2.2.05', 'Material de Limpeza',     'despesa', 'Despesas Operacionais', 240),
('2.2.06', 'Material de Escritório',  'despesa', 'Despesas Operacionais', 250),
('2.2.07', 'Manutenção e Reparos',    'despesa', 'Despesas Operacionais', 260),
('2.2.08', 'Software e Licenças',     'despesa', 'Despesas Operacionais', 270),
('2.3.01', 'Impostos e Taxas',        'despesa', 'Despesas Tributárias', 300),
('2.3.02', 'Contabilidade',           'despesa', 'Despesas Tributárias', 310),
('2.4.01', 'Marketing e Publicidade', 'despesa', 'Despesas Administrativas', 400),
('2.4.02', 'Serviços de Terceiros',   'despesa', 'Despesas Administrativas', 410),
('2.4.03', 'Transportes e Fretes',    'despesa', 'Despesas Administrativas', 420),
('2.4.04', 'Outras Despesas',         'despesa', 'Despesas Administrativas', 430),
('2.5.01', 'Juros e IOF',             'despesa', 'Despesas Financeiras', 500),
('2.5.02', 'Tarifas Bancárias',       'despesa', 'Despesas Financeiras', 510);

-- Contas a Pagar
CREATE TABLE IF NOT EXISTS finance_bills (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    account_id          INT          NULL COMMENT 'FK finance_chart_accounts',
    descricao           VARCHAR(255) NOT NULL,
    fornecedor          VARCHAR(120) NULL,
    valor               DECIMAL(12,2) NOT NULL,
    valor_pago          DECIMAL(12,2) NULL,
    data_vencimento     DATE         NOT NULL,
    data_pagamento      DATE         NULL,
    data_competencia    DATE         NULL COMMENT 'Mês de competência (pode diferir do caixa)',
    status              ENUM('pendente','pago','vencido','cancelado') NOT NULL DEFAULT 'pendente',
    recorrente          TINYINT(1)   NOT NULL DEFAULT 0,
    recorrencia_dia     TINYINT      NULL COMMENT 'Dia do mês para gerar próxima',
    banco_id            INT          NULL COMMENT 'Reservado: integração bancária futura',
    banco_transacao_id  VARCHAR(100) NULL COMMENT 'ID da transação no banco (Open Finance)',
    comprovante_path    VARCHAR(255) NULL,
    observacoes         TEXT         NULL,
    criado_por          INT          NULL,
    criado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES finance_chart_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Contas bancárias (para controle de saldo por conta)
CREATE TABLE IF NOT EXISTS finance_bank_accounts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(80)  NOT NULL COMMENT 'Ex: Conta Corrente Bradesco, Caixa Físico',
    tipo            ENUM('corrente','poupanca','caixa','investimento') NOT NULL DEFAULT 'corrente',
    banco_nome      VARCHAR(80)  NULL,
    agencia         VARCHAR(20)  NULL,
    conta           VARCHAR(30)  NULL,
    saldo_inicial   DECIMAL(12,2) NOT NULL DEFAULT 0,
    saldo_atual     DECIMAL(12,2) NOT NULL DEFAULT 0,
    ativo           TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: conta padrão
INSERT IGNORE INTO finance_bank_accounts (id, nome, tipo) VALUES (1, 'Caixa Geral', 'caixa');
