-- ============================================================
-- Seed: Plano Ensino Médio 2026 — R$ 1.500/mês
-- Insere somente se não existir plano com o mesmo nome + ano_letivo_id
-- ============================================================

-- Detecta o ano letivo atual (mais recente ativo, ou o de menor id se nenhum ativo)
SET @ano_id = (
    SELECT id FROM ano_letivo
    WHERE ativo = 1
    ORDER BY id DESC LIMIT 1
);

-- Insere o plano (idempotente pelo nome)
INSERT INTO finance_plans (nome, descricao, ano_letivo_id, serie_id, ativo)
SELECT
    'Ensino Médio 2026',
    'Plano padrão Ensino Médio: mensalidade + matrícula',
    @ano_id,
    NULL,
    1
WHERE NOT EXISTS (
    SELECT 1 FROM finance_plans WHERE nome = 'Ensino Médio 2026' AND ano_letivo_id = @ano_id
);

SET @plan_id = (SELECT id FROM finance_plans WHERE nome = 'Ensino Médio 2026' AND ano_letivo_id = @ano_id LIMIT 1);

-- Matrícula (parcela única em janeiro)
INSERT INTO finance_plan_items (plan_id, categoria, descricao, valor_base, num_parcelas, mes_inicio, mes_fim, dia_vencimento, fornecedor_externo, nome_instituicao, ordem)
SELECT @plan_id, 'matricula', 'Taxa de Matrícula', 500.00, 1, 1, 1, 10, 0, NULL, 1
WHERE NOT EXISTS (
    SELECT 1 FROM finance_plan_items WHERE plan_id = @plan_id AND categoria = 'matricula'
);

-- Mensalidade (fevereiro a dezembro = 11 parcelas)
INSERT INTO finance_plan_items (plan_id, categoria, descricao, valor_base, num_parcelas, mes_inicio, mes_fim, dia_vencimento, fornecedor_externo, nome_instituicao, ordem)
SELECT @plan_id, 'mensalidade', 'Mensalidade Ensino Médio', 1500.00, 11, 2, 12, 10, 0, NULL, 2
WHERE NOT EXISTS (
    SELECT 1 FROM finance_plan_items WHERE plan_id = @plan_id AND categoria = 'mensalidade'
);

-- Tabela de preços (referência)
INSERT INTO finance_price_table (ano_letivo_id, serie_id, categoria, descricao, valor_base)
SELECT @ano_id, NULL, 'matricula', 'Taxa de Matrícula — Ensino Médio', 500.00
WHERE @ano_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM finance_price_table WHERE ano_letivo_id = @ano_id AND categoria = 'matricula' AND descricao = 'Taxa de Matrícula — Ensino Médio'
);

INSERT INTO finance_price_table (ano_letivo_id, serie_id, categoria, descricao, valor_base)
SELECT @ano_id, NULL, 'mensalidade', 'Mensalidade — Ensino Médio', 1500.00
WHERE @ano_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM finance_price_table WHERE ano_letivo_id = @ano_id AND categoria = 'mensalidade' AND descricao = 'Mensalidade — Ensino Médio'
);

-- Regras de desconto padrão (se não existirem)
INSERT INTO finance_discount_rules (nome, tipo, calculo, valor, acumulavel, requer_aprovacao, ativo)
SELECT 'Desconto Irmãos (2°)', 'irmaos', 'percentual', 10.00, 1, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM finance_discount_rules WHERE nome = 'Desconto Irmãos (2°)');

INSERT INTO finance_discount_rules (nome, tipo, calculo, valor, acumulavel, requer_aprovacao, ativo)
SELECT 'Desconto Irmãos (3° ou +)', 'irmaos', 'percentual', 15.00, 1, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM finance_discount_rules WHERE nome = 'Desconto Irmãos (3° ou +)');

INSERT INTO finance_discount_rules (nome, tipo, calculo, valor, acumulavel, requer_aprovacao, ativo)
SELECT 'Bolsa Integral', 'bolsa', 'percentual', 100.00, 0, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM finance_discount_rules WHERE nome = 'Bolsa Integral');

INSERT INTO finance_discount_rules (nome, tipo, calculo, valor, acumulavel, requer_aprovacao, ativo)
SELECT 'Bolsa Parcial 50%', 'bolsa', 'percentual', 50.00, 1, 1, 1
WHERE NOT EXISTS (SELECT 1 FROM finance_discount_rules WHERE nome = 'Bolsa Parcial 50%');

INSERT INTO finance_discount_rules (nome, tipo, calculo, valor, acumulavel, requer_aprovacao, ativo)
SELECT 'Desconto Pontualidade', 'manual', 'percentual', 5.00, 1, 0, 1
WHERE NOT EXISTS (SELECT 1 FROM finance_discount_rules WHERE nome = 'Desconto Pontualidade');
