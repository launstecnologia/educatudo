-- ============================================================
-- Módulo Financeiro Escolar — tenant
-- 2026_07_02_finance.sql
-- ============================================================

-- Tabela de preços base por categoria/série/ano
CREATE TABLE IF NOT EXISTS finance_price_table (
    id              INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ano_letivo_id   INT NOT NULL,
    serie_id        INT NULL,
    curso_id        INT NULL,
    categoria       ENUM('mensalidade','matricula','material_didatico','uniforme','taxa','outros') NOT NULL,
    descricao       VARCHAR(200) NOT NULL,
    valor_base      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ano_letivo (ano_letivo_id),
    INDEX idx_serie (serie_id),
    INDEX idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regras de desconto configuráveis
CREATE TABLE IF NOT EXISTS finance_discount_rules (
    id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(100) NOT NULL,
    tipo                ENUM('bolsa','irmaos','convenio','funcionario','manual') NOT NULL,
    calculo             ENUM('percentual','fixo') NOT NULL DEFAULT 'percentual',
    valor               DECIMAL(10,2) NOT NULL,
    acumulavel          TINYINT(1) NOT NULL DEFAULT 0,
    limite_acumulado    DECIMAL(10,2) NULL,
    categorias_aplicaveis JSON NULL,
    requer_aprovacao    TINYINT(1) NOT NULL DEFAULT 0,
    ativo               TINYINT(1) NOT NULL DEFAULT 1,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo (tipo),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contratos financeiros por aluno/ano_letivo
CREATE TABLE IF NOT EXISTS finance_contracts (
    id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    aluno_id            INT NOT NULL,
    matricula_id        INT NULL,
    enrollment_id       INT NULL,
    ano_letivo_id       INT NOT NULL,
    responsavel_id      INT NULL,
    responsavel_nome    VARCHAR(200) NOT NULL DEFAULT '',
    responsavel_cpf     VARCHAR(14) NULL,
    responsavel_email   VARCHAR(200) NULL,
    responsavel_telefone VARCHAR(20) NULL,
    valor_bruto         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_desconto      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_liquido       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status              ENUM('rascunho','ativo','cancelado','encerrado') NOT NULL DEFAULT 'rascunho',
    plano_pagamento     ENUM('mensal','semestral','anual','avulso') NOT NULL DEFAULT 'mensal',
    num_parcelas        INT NOT NULL DEFAULT 12,
    dia_vencimento      TINYINT NOT NULL DEFAULT 10,
    mes_inicio          TINYINT NOT NULL DEFAULT 1,
    mes_fim             TINYINT NOT NULL DEFAULT 12,
    observacoes         TEXT NULL,
    criado_por          INT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_aluno (aluno_id),
    INDEX idx_ano_letivo (ano_letivo_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Descontos aplicados a cada contrato (rastreável)
CREATE TABLE IF NOT EXISTS finance_contract_discounts (
    id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contract_id         INT NOT NULL,
    discount_rule_id    INT NULL,
    tipo                VARCHAR(50) NOT NULL,
    descricao           VARCHAR(200) NOT NULL,
    calculo             ENUM('percentual','fixo') NOT NULL DEFAULT 'percentual',
    valor               DECIMAL(10,2) NOT NULL,
    valor_aplicado      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    irmao_aluno_id      INT NULL,
    aprovado_por        INT NULL,
    aprovado_em         DATETIME NULL,
    status              ENUM('pendente','aprovado','rejeitado') NOT NULL DEFAULT 'aprovado',
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contract (contract_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parcelas geradas do contrato
CREATE TABLE IF NOT EXISTS finance_installments (
    id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contract_id         INT NOT NULL,
    num_parcela         INT NOT NULL,
    categoria           ENUM('mensalidade','matricula','material_didatico','uniforme','taxa','outros') NOT NULL,
    descricao           VARCHAR(200) NOT NULL,
    valor_nominal       DECIMAL(10,2) NOT NULL,
    valor_desconto      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    valor_cobrado       DECIMAL(10,2) NOT NULL,
    data_vencimento     DATE NOT NULL,
    data_pagamento      DATE NULL,
    valor_pago          DECIMAL(10,2) NULL,
    juros_aplicado      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    multa_aplicada      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status              ENUM('pendente','pago','vencido','cancelado','renegociado') NOT NULL DEFAULT 'pendente',
    boleto_codigo       VARCHAR(100) NULL,
    boleto_gerado_em    DATETIME NULL,
    observacoes         TEXT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_contract (contract_id),
    INDEX idx_vencimento (data_vencimento),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Baixas de pagamento
CREATE TABLE IF NOT EXISTS finance_payments (
    id                  INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    installment_id      INT NOT NULL,
    valor               DECIMAL(10,2) NOT NULL,
    data_pagamento      DATE NOT NULL,
    forma_pagamento     ENUM('dinheiro','pix','boleto','transferencia','cartao','outro') NOT NULL DEFAULT 'outro',
    referencia          VARCHAR(100) NULL,
    observacoes         TEXT NULL,
    registrado_por      INT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_installment (installment_id),
    INDEX idx_data (data_pagamento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trilha de auditoria financeira
CREATE TABLE IF NOT EXISTS finance_audit (
    id          INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    entidade    ENUM('contract','installment','payment','discount') NOT NULL,
    entidade_id INT NOT NULL,
    acao        VARCHAR(50) NOT NULL,
    dados_antes JSON NULL,
    dados_depois JSON NULL,
    usuario_id  INT NULL,
    usuario_nome VARCHAR(200) NULL,
    ip          VARCHAR(45) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entidade (entidade, entidade_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Régua de cobrança configurável
CREATE TABLE IF NOT EXISTS billing_rule_config (
    id              INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(100) NOT NULL,
    dias_relativo   INT NOT NULL,
    canal           ENUM('app','email','whatsapp') NOT NULL,
    template_titulo VARCHAR(200) NOT NULL,
    template_corpo  TEXT NOT NULL,
    ativo           TINYINT(1) NOT NULL DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de mensagens de cobrança enviadas
CREATE TABLE IF NOT EXISTS billing_message_log (
    id              INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    installment_id  INT NOT NULL,
    aluno_id        INT NOT NULL,
    responsavel_id  INT NULL,
    canal           ENUM('app','email','whatsapp') NOT NULL,
    template_usado  VARCHAR(100) NULL,
    destinatario    VARCHAR(200) NULL,
    status          ENUM('enviado','falha','simulado') NOT NULL DEFAULT 'simulado',
    erro            TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_installment (installment_id),
    INDEX idx_aluno (aluno_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Régua padrão: D-3 lembrete, D0 vencimento, D+5 atraso, D+30 inadimplência
INSERT IGNORE INTO billing_rule_config (nome, dias_relativo, canal, template_titulo, template_corpo) VALUES
('Lembrete 3 dias antes', -3, 'app', 'Parcela vence em 3 dias', 'Olá {aluno_nome}, a parcela {descricao} no valor de R$ {valor} vence em {data_vencimento}.'),
('Lembrete 3 dias antes', -3, 'email', 'Lembrete de vencimento — {descricao}', 'Olá {responsavel_nome},\n\nLembramos que a parcela referente a {descricao} do(a) aluno(a) {aluno_nome} vence em {data_vencimento} no valor de R$ {valor}.\n\nEducaTudo'),
('Dia do vencimento', 0, 'app', 'Parcela vence hoje', 'Atenção! A parcela {descricao} de R$ {valor} vence hoje ({data_vencimento}).'),
('Dia do vencimento', 0, 'whatsapp', 'Vencimento hoje', 'Olá {responsavel_nome}! A mensalidade de {aluno_nome} ({descricao}) vence *hoje* — R$ {valor}. Em caso de dúvidas, entre em contato com a secretaria.'),
('Atraso 5 dias', 5, 'app', 'Parcela em atraso', 'A parcela {descricao} de R$ {valor} está em atraso há 5 dias.'),
('Atraso 5 dias', 5, 'email', 'Parcela em atraso — {descricao}', 'Olá {responsavel_nome},\n\nA parcela de {aluno_nome} referente a {descricao} está em atraso (venceu em {data_vencimento}). Por favor, regularize para evitar encargos adicionais.\n\nEducaTudo'),
('Atraso 5 dias', 5, 'whatsapp', 'Aviso de atraso', 'Olá {responsavel_nome}! A mensalidade de *{aluno_nome}* ({descricao}) está em atraso desde {data_vencimento}. Valor: R$ {valor}. Regularize com a secretaria.'),
('Inadimplência 30 dias', 30, 'app', 'Débito pendente — ação necessária', 'O débito {descricao} de R$ {valor} está há 30 dias em aberto. Procure a secretaria.'),
('Inadimplência 30 dias', 30, 'email', 'Notificação de inadimplência', 'Olá {responsavel_nome},\n\nInformamos que a parcela de {aluno_nome} referente a {descricao} encontra-se em atraso há 30 dias. Regularize o quanto antes para evitar maiores complicações.\n\nSecretaria — {escola_nome}');
