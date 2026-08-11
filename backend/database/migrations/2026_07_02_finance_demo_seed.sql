-- ============================================================
-- Demo Seed: Matrículas + Contratos Financeiros 2026
-- Alunos: João Silva(3), Maria Santos(4), Pedro Oliveira(5),
--         Lucas Ferreira(7), Lucas Moraes(8)
-- Plano: Ensino Médio 2026 (id=2)
-- Items plano: Matrícula R$500 (id=3), Mensalidade R$1500 (id=4)
-- Ano letivo 2026 (id=2)
-- Idempotente: só insere se não existir
-- ============================================================

SET @ano_letivo_id = 2;
SET @plan_id        = 2;
SET @item_matricula = 3;  -- Taxa de Matrícula R$500
SET @item_mensal    = 4;  -- Mensalidade R$1500

-- ============================================================
-- 1. MATRÍCULAS (enrollment)
-- ============================================================

INSERT IGNORE INTO enrollment
    (id, tipo, status, aluno_id, ano_letivo_id, turma_id,
     aluno_nome, resp_nome, resp_cpf, resp_email, resp_telefone,
     resp_parentesco, origem, created_at)
VALUES
-- João Silva → 3ºA (turma 5), resp Marco Morais(4)
(101, 'rematricula', 'confirmada', 3, @ano_letivo_id, 5,
 'João Silva', 'Marco Morais', '123.456.789-00', 'marco@email.com', '(11) 99999-0001',
 'Pai', 'interno', '2026-01-10 09:00:00'),

-- Maria Santos → 2ºA (turma 3), sem responsável vinculado
(102, 'rematricula', 'confirmada', 4, @ano_letivo_id, 3,
 'Maria Santos', 'Ana Santos', '234.567.890-00', 'ana@email.com', '(11) 99999-0002',
 'Mãe', 'interno', '2026-01-10 09:30:00'),

-- Pedro Oliveira → 2ºB (turma 4), sem responsável vinculado
(103, 'rematricula', 'confirmada', 5, @ano_letivo_id, 4,
 'Pedro Oliveira', 'Carlos Oliveira', '345.678.901-00', 'carlos@email.com', '(11) 99999-0003',
 'Pai', 'interno', '2026-01-11 08:00:00'),

-- Lucas Ferreira → 1ºA (turma 1), resp Marco Morais(2)
(104, 'nova', 'confirmada', 7, @ano_letivo_id, 1,
 'Lucas Ferreira', 'Marco Morais', '123.456.789-00', 'marco@email.com', '(11) 99999-0001',
 'Pai', 'site', '2026-01-12 10:00:00'),

-- Lucas Moraes → 1ºB (turma 2), sem responsável vinculado
(105, 'nova', 'confirmada', 8, @ano_letivo_id, 2,
 'Lucas Moraes', 'Roberto Moraes', '456.789.012-00', 'roberto@email.com', '(11) 99999-0004',
 'Pai', 'indicacao', '2026-01-12 11:00:00');

-- ============================================================
-- 2. CONTRATOS FINANCEIROS (finance_contracts)
-- ============================================================
-- valor_bruto = R$500 matrícula + 11 × R$1500 = R$17.000
-- sem desconto nos dados de demo

INSERT IGNORE INTO finance_contracts
    (id, aluno_id, enrollment_id, plan_id, ano_letivo_id,
     responsavel_id, responsavel_nome, responsavel_cpf, responsavel_email, responsavel_telefone,
     valor_bruto, valor_desconto, valor_liquido,
     status, plano_pagamento, num_parcelas, dia_vencimento, mes_inicio, mes_fim,
     created_at)
VALUES
(101, 3, 101, @plan_id, @ano_letivo_id,
 4, 'Marco Morais', '123.456.789-00', 'marco@email.com', '(11) 99999-0001',
 17000.00, 0.00, 17000.00,
 'ativo', 'mensal', 12, 10, 1, 12, '2026-01-10 09:10:00'),

(102, 4, 102, @plan_id, @ano_letivo_id,
 NULL, 'Ana Santos', '234.567.890-00', 'ana@email.com', '(11) 99999-0002',
 17000.00, 500.00, 16500.00,   -- desconto de R$500 no total (ex: desconto irmão)
 'ativo', 'mensal', 12, 10, 1, 12, '2026-01-10 09:40:00'),

(103, 5, 103, @plan_id, @ano_letivo_id,
 NULL, 'Carlos Oliveira', '345.678.901-00', 'carlos@email.com', '(11) 99999-0003',
 17000.00, 0.00, 17000.00,
 'ativo', 'mensal', 12, 10, 1, 12, '2026-01-11 08:10:00'),

(104, 7, 104, @plan_id, @ano_letivo_id,
 2, 'Marco Morais', '123.456.789-00', 'marco@email.com', '(11) 99999-0001',
 17000.00, 850.00, 16150.00,   -- desconto 5% irmão
 'ativo', 'mensal', 12, 10, 1, 12, '2026-01-12 10:10:00'),

(105, 8, 105, @plan_id, @ano_letivo_id,
 NULL, 'Roberto Moraes', '456.789.012-00', 'roberto@email.com', '(11) 99999-0004',
 17000.00, 0.00, 17000.00,
 'ativo', 'mensal', 12, 10, 1, 12, '2026-01-12 11:10:00');

-- ============================================================
-- 3. ITENS DE CONTRATO (finance_contract_items)
-- ============================================================

INSERT IGNORE INTO finance_contract_items
    (id, contract_id, plan_item_id, categoria, descricao,
     valor_unitario, quantidade, valor_total, valor_desconto, valor_liquido,
     num_parcelas, mes_inicio, mes_fim, dia_vencimento, status)
VALUES
-- Contrato 101 (João Silva)
(1001, 101, @item_matricula, 'matricula', 'Taxa de Matrícula',     500.00, 1, 500.00,  0.00, 500.00,  1,  1,  1, 10, 'ativo'),
(1002, 101, @item_mensal,    'mensalidade','Mensalidade Ensino Médio',1500.00,1,16500.00,0.00,16500.00,11,  2, 12, 10, 'ativo'),

-- Contrato 102 (Maria Santos — desconto de R$500 na mensalidade)
(1003, 102, @item_matricula, 'matricula', 'Taxa de Matrícula',     500.00, 1, 500.00,  0.00, 500.00,  1,  1,  1, 10, 'ativo'),
(1004, 102, @item_mensal,    'mensalidade','Mensalidade Ensino Médio',1500.00,1,16500.00,500.00,16000.00,11,2,12,10, 'ativo'),

-- Contrato 103 (Pedro Oliveira)
(1005, 103, @item_matricula, 'matricula', 'Taxa de Matrícula',     500.00, 1, 500.00,  0.00, 500.00,  1,  1,  1, 10, 'ativo'),
(1006, 103, @item_mensal,    'mensalidade','Mensalidade Ensino Médio',1500.00,1,16500.00,0.00,16500.00,11,  2, 12, 10, 'ativo'),

-- Contrato 104 (Lucas Ferreira — desconto irmão R$850)
(1007, 104, @item_matricula, 'matricula', 'Taxa de Matrícula',     500.00, 1, 500.00,  0.00, 500.00,  1,  1,  1, 10, 'ativo'),
(1008, 104, @item_mensal,    'mensalidade','Mensalidade Ensino Médio',1500.00,1,16500.00,850.00,15650.00,11,2,12,10, 'ativo'),

-- Contrato 105 (Lucas Moraes)
(1009, 105, @item_matricula, 'matricula', 'Taxa de Matrícula',     500.00, 1, 500.00,  0.00, 500.00,  1,  1,  1, 10, 'ativo'),
(1010, 105, @item_mensal,    'mensalidade','Mensalidade Ensino Médio',1500.00,1,16500.00,0.00,16500.00,11,  2, 12, 10, 'ativo');

-- ============================================================
-- 4. PARCELAS (finance_installments)
-- Situação realista em Julho/2026:
--   Jan (matrícula) → pago
--   Fev–Jun (mensalidades) → pago
--   Jul → vencido (hoje é 02/07/2026, vencimento dia 10)
--   Ago–Dez → pendente
-- ============================================================

INSERT IGNORE INTO finance_installments
    (contract_id, contract_item_id, num_parcela, categoria, descricao,
     valor_nominal, valor_desconto, valor_cobrado,
     data_vencimento, data_pagamento, valor_pago,
     status, created_at)
SELECT
    ci.contract_id,
    ci.id,
    s.n,
    ci.categoria,
    CASE ci.categoria
        WHEN 'matricula'    THEN 'Taxa de Matrícula'
        WHEN 'mensalidade'  THEN CONCAT('Mensalidade ', LPAD(s.mes, 2, '0'), '/2026')
    END,
    -- valor nominal por parcela
    CASE ci.categoria
        WHEN 'matricula'   THEN ci.valor_liquido
        WHEN 'mensalidade' THEN ROUND(ci.valor_liquido / ci.num_parcelas, 2)
    END,
    0.00,
    CASE ci.categoria
        WHEN 'matricula'   THEN ci.valor_liquido
        WHEN 'mensalidade' THEN ROUND(ci.valor_liquido / ci.num_parcelas, 2)
    END,
    -- data vencimento
    CASE ci.categoria
        WHEN 'matricula'   THEN '2026-01-10'
        WHEN 'mensalidade' THEN DATE(CONCAT('2026-', LPAD(s.mes, 2, '0'), '-10'))
    END,
    -- data pagamento (pagas até jun/2026)
    CASE
        WHEN ci.categoria = 'matricula' THEN '2026-01-08'
        WHEN ci.categoria = 'mensalidade' AND s.mes <= 6 THEN DATE(CONCAT('2026-', LPAD(s.mes, 2, '0'), '-09'))
        ELSE NULL
    END,
    -- valor pago
    CASE
        WHEN ci.categoria = 'matricula' THEN ci.valor_liquido
        WHEN ci.categoria = 'mensalidade' AND s.mes <= 6 THEN ROUND(ci.valor_liquido / ci.num_parcelas, 2)
        ELSE NULL
    END,
    -- status
    CASE
        WHEN ci.categoria = 'matricula' THEN 'pago'
        WHEN ci.categoria = 'mensalidade' AND s.mes <= 6  THEN 'pago'
        WHEN ci.categoria = 'mensalidade' AND s.mes = 7   THEN 'vencido'
        ELSE 'pendente'
    END,
    NOW()
FROM finance_contract_items ci
-- série de meses para mensalidade (2–12 = 11 parcelas), matrícula usa mês=1 uma vez
JOIN (
    SELECT 1 AS n, 1 AS mes UNION ALL
    SELECT 2, 2  UNION ALL SELECT 3, 3  UNION ALL SELECT 4, 4  UNION ALL
    SELECT 5, 5  UNION ALL SELECT 6, 6  UNION ALL SELECT 7, 7  UNION ALL
    SELECT 8, 8  UNION ALL SELECT 9, 9  UNION ALL SELECT 10,10 UNION ALL
    SELECT 11,11 UNION ALL SELECT 12,12
) s ON (
       (ci.categoria = 'matricula'   AND s.n = 1)
    OR (ci.categoria = 'mensalidade' AND s.mes BETWEEN ci.mes_inicio AND ci.mes_fim)
)
WHERE ci.contract_id IN (101,102,103,104,105)
  AND NOT EXISTS (
      SELECT 1 FROM finance_installments fi
      WHERE fi.contract_id = ci.contract_id
        AND fi.contract_item_id = ci.id
        AND fi.num_parcela = s.n
  );

-- ============================================================
-- 5. LEDGER: entradas para parcelas pagas
-- ============================================================

INSERT INTO finance_ledger
    (aluno_id, contract_id, referencia_tipo, referencia_id,
     tipo, categoria, descricao, valor, saldo_acumulado,
     data_lancamento, gerado_auto, created_at)
SELECT
    fc.aluno_id,
    fi.contract_id,
    'installment',
    fi.id,
    'credito',
    fi.categoria,
    CONCAT('Pagamento ', fi.descricao),
    fi.valor_cobrado,
    0.00,
    fi.data_pagamento,
    1,
    NOW()
FROM finance_installments fi
JOIN finance_contracts fc ON fc.id = fi.contract_id
WHERE fi.contract_id IN (101,102,103,104,105)
  AND fi.status = 'pago'
  AND fi.data_pagamento IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM finance_ledger fl
      WHERE fl.referencia_tipo = 'installment'
        AND fl.referencia_id = fi.id
        AND fl.tipo = 'credito'
  );
