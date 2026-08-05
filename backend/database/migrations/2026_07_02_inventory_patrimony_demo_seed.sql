-- ============================================================
-- Seed demo — Almoxarifado e Patrimônio Escolar
-- 2026_07_02_inventory_patrimony_demo_seed.sql
--
-- Executar depois de:
-- database/migrations/2026_07_02_inventory_patrimony.sql
-- ============================================================

-- Ambientes físicos compartilhados com Patrimônio
INSERT INTO school_locations (codigo, nome, tipo, bloco, andar, responsavel_nome, ativo)
SELECT 'ADM-SEC', 'Secretaria', 'secretaria', 'Bloco Administrativo', 'Térreo', 'Secretaria Escolar', 1
WHERE NOT EXISTS (SELECT 1 FROM school_locations WHERE codigo = 'ADM-SEC');

INSERT INTO school_locations (codigo, nome, tipo, bloco, andar, responsavel_nome, ativo)
SELECT 'SALA-6A', 'Sala 6º Ano A', 'sala', 'Bloco A', '1º andar', 'Coordenação Pedagógica', 1
WHERE NOT EXISTS (SELECT 1 FROM school_locations WHERE codigo = 'SALA-6A');

INSERT INTO school_locations (codigo, nome, tipo, bloco, andar, responsavel_nome, ativo)
SELECT 'LAB-INF', 'Laboratório de Informática', 'laboratorio', 'Bloco B', 'Térreo', 'Professor de Tecnologia', 1
WHERE NOT EXISTS (SELECT 1 FROM school_locations WHERE codigo = 'LAB-INF');

INSERT INTO school_locations (codigo, nome, tipo, bloco, andar, responsavel_nome, ativo)
SELECT 'CANTINA', 'Cantina', 'cantina', 'Bloco Serviços', 'Térreo', 'Equipe de Merenda', 1
WHERE NOT EXISTS (SELECT 1 FROM school_locations WHERE codigo = 'CANTINA');

INSERT INTO school_locations (codigo, nome, tipo, bloco, andar, responsavel_nome, ativo)
SELECT 'DEP-CENTRAL', 'Depósito Central', 'deposito', 'Bloco Serviços', 'Térreo', 'Almoxarifado', 1
WHERE NOT EXISTS (SELECT 1 FROM school_locations WHERE codigo = 'DEP-CENTRAL');

INSERT INTO school_locations (codigo, nome, tipo, bloco, andar, responsavel_nome, ativo)
SELECT 'LAB-CIEN', 'Laboratório de Ciências', 'laboratorio', 'Bloco B', '1º andar', 'Professor de Ciências', 1
WHERE NOT EXISTS (SELECT 1 FROM school_locations WHERE codigo = 'LAB-CIEN');

-- Fornecedores
INSERT INTO inventory_suppliers (nome, cnpj, contato, telefone, email, observacoes, ativo)
SELECT 'Distribuidora Escolar Alfa', '12.345.678/0001-90', 'Mariana Lima', '(11) 4002-1000', 'compras@alfa.example', 'Fornecedor recorrente de material didático e escritório.', 1
WHERE NOT EXISTS (SELECT 1 FROM inventory_suppliers WHERE cnpj = '12.345.678/0001-90');

INSERT INTO inventory_suppliers (nome, cnpj, contato, telefone, email, observacoes, ativo)
SELECT 'Limpeza Total Atacado', '22.222.222/0001-22', 'Carlos Souza', '(11) 4002-2000', 'vendas@limpezatotal.example', 'Produtos de limpeza e higiene.', 1
WHERE NOT EXISTS (SELECT 1 FROM inventory_suppliers WHERE cnpj = '22.222.222/0001-22');

INSERT INTO inventory_suppliers (nome, cnpj, contato, telefone, email, observacoes, ativo)
SELECT 'NutriMerenda Comércio', '33.333.333/0001-33', 'Ana Pereira', '(11) 4002-3000', 'pedidos@nutrimerenda.example', 'Fornecedor de merenda escolar.', 1
WHERE NOT EXISTS (SELECT 1 FROM inventory_suppliers WHERE cnpj = '33.333.333/0001-33');

INSERT INTO inventory_suppliers (nome, cnpj, contato, telefone, email, observacoes, ativo)
SELECT 'TechEdu Equipamentos', '44.444.444/0001-44', 'Rafael Costa', '(11) 4002-4000', 'suporte@techedu.example', 'Equipamentos de informática e projetores.', 1
WHERE NOT EXISTS (SELECT 1 FROM inventory_suppliers WHERE cnpj = '44.444.444/0001-44');

-- Depósitos / almoxarifados
INSERT INTO inventory_warehouses (nome, tipo, location_id, responsavel_nome, ativo)
SELECT 'Almoxarifado Central', 'central', (SELECT id FROM school_locations WHERE codigo = 'DEP-CENTRAL' LIMIT 1), 'João Almoxarife', 1
WHERE NOT EXISTS (SELECT 1 FROM inventory_warehouses WHERE nome = 'Almoxarifado Central');

INSERT INTO inventory_warehouses (nome, tipo, location_id, responsavel_nome, ativo)
SELECT 'Cantina / Merenda', 'cantina', (SELECT id FROM school_locations WHERE codigo = 'CANTINA' LIMIT 1), 'Equipe de Merenda', 1
WHERE NOT EXISTS (SELECT 1 FROM inventory_warehouses WHERE nome = 'Cantina / Merenda');

INSERT INTO inventory_warehouses (nome, tipo, location_id, responsavel_nome, ativo)
SELECT 'Laboratório de Ciências', 'laboratorio', (SELECT id FROM school_locations WHERE codigo = 'LAB-CIEN' LIMIT 1), 'Professor de Ciências', 1
WHERE NOT EXISTS (SELECT 1 FROM inventory_warehouses WHERE nome = 'Laboratório de Ciências');

INSERT INTO inventory_warehouses (nome, tipo, location_id, responsavel_nome, ativo)
SELECT 'Secretaria', 'secretaria', (SELECT id FROM school_locations WHERE codigo = 'ADM-SEC' LIMIT 1), 'Secretaria Escolar', 1
WHERE NOT EXISTS (SELECT 1 FROM inventory_warehouses WHERE nome = 'Secretaria');

-- Catálogo de itens de consumo
INSERT INTO inventory_items (codigo, descricao, unidade_medida, categoria, estoque_minimo, estoque_maximo, ponto_reposicao, custo_medio, ativo)
VALUES
('LIMP-001', 'Detergente neutro 5L', 'galão', 'limpeza', 5, 30, 8, 18.90, 1),
('LIMP-002', 'Desinfetante 5L', 'galão', 'limpeza', 6, 40, 10, 16.50, 1),
('HIG-001', 'Papel toalha interfolha', 'pacote', 'higiene', 20, 120, 30, 12.80, 1),
('ESC-001', 'Papel sulfite A4 500 folhas', 'resma', 'escritorio', 25, 180, 40, 24.90, 1),
('ESC-002', 'Caneta esferográfica azul', 'un', 'escritorio', 50, 400, 80, 0.95, 1),
('DID-001', 'Cartolina colorida', 'folha', 'didatico', 80, 600, 120, 0.85, 1),
('MER-001', 'Arroz tipo 1 5kg', 'pacote', 'merenda', 10, 80, 20, 27.50, 1),
('MER-002', 'Feijão carioca 1kg', 'pacote', 'merenda', 15, 120, 25, 8.90, 1),
('LAB-001', 'Álcool 70% 1L', 'frasco', 'laboratorio', 8, 60, 12, 9.70, 1)
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao),
    unidade_medida = VALUES(unidade_medida),
    categoria = VALUES(categoria),
    estoque_minimo = VALUES(estoque_minimo),
    estoque_maximo = VALUES(estoque_maximo),
    ponto_reposicao = VALUES(ponto_reposicao),
    custo_medio = VALUES(custo_medio),
    ativo = VALUES(ativo),
    updated_at = NOW();

-- Lotes com validade e custo. Não duplica pelo par item/deposito/lote.
INSERT INTO inventory_lots (item_id, warehouse_id, lote, validade, quantidade_atual, custo_unitario, entrada_em)
SELECT i.id, w.id, 'NF1001-A', DATE_ADD(CURDATE(), INTERVAL 180 DAY), 18, 18.90, DATE_SUB(NOW(), INTERVAL 20 DAY)
FROM inventory_items i CROSS JOIN inventory_warehouses w
WHERE i.codigo = 'LIMP-001' AND w.nome = 'Almoxarifado Central'
AND NOT EXISTS (SELECT 1 FROM inventory_lots l WHERE l.item_id = i.id AND l.warehouse_id = w.id AND l.lote = 'NF1001-A');

INSERT INTO inventory_lots (item_id, warehouse_id, lote, validade, quantidade_atual, custo_unitario, entrada_em)
SELECT i.id, w.id, 'NF1002-B', DATE_ADD(CURDATE(), INTERVAL 25 DAY), 7, 16.50, DATE_SUB(NOW(), INTERVAL 35 DAY)
FROM inventory_items i CROSS JOIN inventory_warehouses w
WHERE i.codigo = 'LIMP-002' AND w.nome = 'Almoxarifado Central'
AND NOT EXISTS (SELECT 1 FROM inventory_lots l WHERE l.item_id = i.id AND l.warehouse_id = w.id AND l.lote = 'NF1002-B');

INSERT INTO inventory_lots (item_id, warehouse_id, lote, validade, quantidade_atual, custo_unitario, entrada_em)
SELECT i.id, w.id, 'NF1003-C', DATE_ADD(CURDATE(), INTERVAL 365 DAY), 96, 12.80, DATE_SUB(NOW(), INTERVAL 12 DAY)
FROM inventory_items i CROSS JOIN inventory_warehouses w
WHERE i.codigo = 'HIG-001' AND w.nome = 'Almoxarifado Central'
AND NOT EXISTS (SELECT 1 FROM inventory_lots l WHERE l.item_id = i.id AND l.warehouse_id = w.id AND l.lote = 'NF1003-C');

INSERT INTO inventory_lots (item_id, warehouse_id, lote, validade, quantidade_atual, custo_unitario, entrada_em)
SELECT i.id, w.id, 'NF1004-D', NULL, 64, 24.90, DATE_SUB(NOW(), INTERVAL 15 DAY)
FROM inventory_items i CROSS JOIN inventory_warehouses w
WHERE i.codigo = 'ESC-001' AND w.nome = 'Secretaria'
AND NOT EXISTS (SELECT 1 FROM inventory_lots l WHERE l.item_id = i.id AND l.warehouse_id = w.id AND l.lote = 'NF1004-D');

INSERT INTO inventory_lots (item_id, warehouse_id, lote, validade, quantidade_atual, custo_unitario, entrada_em)
SELECT i.id, w.id, 'NF1005-E', NULL, 240, 0.95, DATE_SUB(NOW(), INTERVAL 10 DAY)
FROM inventory_items i CROSS JOIN inventory_warehouses w
WHERE i.codigo = 'ESC-002' AND w.nome = 'Secretaria'
AND NOT EXISTS (SELECT 1 FROM inventory_lots l WHERE l.item_id = i.id AND l.warehouse_id = w.id AND l.lote = 'NF1005-E');

INSERT INTO inventory_lots (item_id, warehouse_id, lote, validade, quantidade_atual, custo_unitario, entrada_em)
SELECT i.id, w.id, 'NF1006-F', NULL, 320, 0.85, DATE_SUB(NOW(), INTERVAL 18 DAY)
FROM inventory_items i CROSS JOIN inventory_warehouses w
WHERE i.codigo = 'DID-001' AND w.nome = 'Almoxarifado Central'
AND NOT EXISTS (SELECT 1 FROM inventory_lots l WHERE l.item_id = i.id AND l.warehouse_id = w.id AND l.lote = 'NF1006-F');

INSERT INTO inventory_lots (item_id, warehouse_id, lote, validade, quantidade_atual, custo_unitario, entrada_em)
SELECT i.id, w.id, 'MER2026-01', DATE_ADD(CURDATE(), INTERVAL 90 DAY), 38, 27.50, DATE_SUB(NOW(), INTERVAL 8 DAY)
FROM inventory_items i CROSS JOIN inventory_warehouses w
WHERE i.codigo = 'MER-001' AND w.nome = 'Cantina / Merenda'
AND NOT EXISTS (SELECT 1 FROM inventory_lots l WHERE l.item_id = i.id AND l.warehouse_id = w.id AND l.lote = 'MER2026-01');

INSERT INTO inventory_lots (item_id, warehouse_id, lote, validade, quantidade_atual, custo_unitario, entrada_em)
SELECT i.id, w.id, 'MER2026-02', DATE_ADD(CURDATE(), INTERVAL 45 DAY), 22, 8.90, DATE_SUB(NOW(), INTERVAL 8 DAY)
FROM inventory_items i CROSS JOIN inventory_warehouses w
WHERE i.codigo = 'MER-002' AND w.nome = 'Cantina / Merenda'
AND NOT EXISTS (SELECT 1 FROM inventory_lots l WHERE l.item_id = i.id AND l.warehouse_id = w.id AND l.lote = 'MER2026-02');

INSERT INTO inventory_lots (item_id, warehouse_id, lote, validade, quantidade_atual, custo_unitario, entrada_em)
SELECT i.id, w.id, 'LAB2026-01', DATE_ADD(CURDATE(), INTERVAL 20 DAY), 9, 9.70, DATE_SUB(NOW(), INTERVAL 30 DAY)
FROM inventory_items i CROSS JOIN inventory_warehouses w
WHERE i.codigo = 'LAB-001' AND w.nome = 'Laboratório de Ciências'
AND NOT EXISTS (SELECT 1 FROM inventory_lots l WHERE l.item_id = i.id AND l.warehouse_id = w.id AND l.lote = 'LAB2026-01');

-- Movimentações demonstrativas de entrada
INSERT INTO inventory_movements (item_id, warehouse_id, lot_id, tipo, quantidade, custo_unitario, documento, fornecedor_id, setor, motivo, realizado_por, created_at)
SELECT i.id, w.id, l.id, 'entrada', l.quantidade_atual, l.custo_unitario, 'NF-1001', s.id, 'Almoxarifado', 'Carga inicial de demonstração', NULL, l.entrada_em
FROM inventory_items i
JOIN inventory_warehouses w ON w.nome = 'Almoxarifado Central'
JOIN inventory_lots l ON l.item_id = i.id AND l.warehouse_id = w.id
LEFT JOIN inventory_suppliers s ON s.cnpj = '22.222.222/0001-22'
WHERE i.codigo IN ('LIMP-001', 'LIMP-002', 'HIG-001', 'DID-001')
AND NOT EXISTS (
    SELECT 1 FROM inventory_movements m
    WHERE m.item_id = i.id AND m.warehouse_id = w.id AND m.lot_id = l.id AND m.tipo = 'entrada' AND m.documento = 'NF-1001'
);

INSERT INTO inventory_movements (item_id, warehouse_id, lot_id, tipo, quantidade, custo_unitario, documento, fornecedor_id, setor, motivo, realizado_por, created_at)
SELECT i.id, w.id, l.id, 'entrada', l.quantidade_atual, l.custo_unitario, 'NF-2001', s.id, 'Secretaria', 'Carga inicial de demonstração', NULL, l.entrada_em
FROM inventory_items i
JOIN inventory_warehouses w ON w.nome = 'Secretaria'
JOIN inventory_lots l ON l.item_id = i.id AND l.warehouse_id = w.id
LEFT JOIN inventory_suppliers s ON s.cnpj = '12.345.678/0001-90'
WHERE i.codigo IN ('ESC-001', 'ESC-002')
AND NOT EXISTS (
    SELECT 1 FROM inventory_movements m
    WHERE m.item_id = i.id AND m.warehouse_id = w.id AND m.lot_id = l.id AND m.tipo = 'entrada' AND m.documento = 'NF-2001'
);

INSERT INTO inventory_movements (item_id, warehouse_id, lot_id, tipo, quantidade, custo_unitario, documento, fornecedor_id, setor, motivo, realizado_por, created_at)
SELECT i.id, w.id, l.id, 'entrada', l.quantidade_atual, l.custo_unitario, 'NF-3001', s.id, 'Merenda', 'Carga inicial de demonstração', NULL, l.entrada_em
FROM inventory_items i
JOIN inventory_warehouses w ON w.nome = 'Cantina / Merenda'
JOIN inventory_lots l ON l.item_id = i.id AND l.warehouse_id = w.id
LEFT JOIN inventory_suppliers s ON s.cnpj = '33.333.333/0001-33'
WHERE i.codigo IN ('MER-001', 'MER-002')
AND NOT EXISTS (
    SELECT 1 FROM inventory_movements m
    WHERE m.item_id = i.id AND m.warehouse_id = w.id AND m.lot_id = l.id AND m.tipo = 'entrada' AND m.documento = 'NF-3001'
);

-- Requisições internas
INSERT INTO inventory_requisitions (item_id, warehouse_id, quantidade, setor, solicitante_nome, justificativa, status, aprovado_em, atendido_em, observacoes, created_at)
SELECT i.id, w.id, 12, 'Sala 6º Ano A', 'Prof. Fernanda Rocha', 'Atividade interdisciplinar de cartazes.', 'atendida', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), 'Atendida parcialmente com cartolina colorida.', DATE_SUB(NOW(), INTERVAL 6 DAY)
FROM inventory_items i CROSS JOIN inventory_warehouses w
WHERE i.codigo = 'DID-001' AND w.nome = 'Almoxarifado Central'
AND NOT EXISTS (SELECT 1 FROM inventory_requisitions r WHERE r.item_id = i.id AND r.solicitante_nome = 'Prof. Fernanda Rocha' AND r.justificativa = 'Atividade interdisciplinar de cartazes.');

INSERT INTO inventory_requisitions (item_id, warehouse_id, quantidade, setor, solicitante_nome, justificativa, status, observacoes, created_at)
SELECT i.id, w.id, 4, 'Coordenação', 'Coordenação Pedagógica', 'Reposição para impressões de avaliações.', 'aprovada', 'Aguardando retirada na secretaria.', DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM inventory_items i CROSS JOIN inventory_warehouses w
WHERE i.codigo = 'ESC-001' AND w.nome = 'Secretaria'
AND NOT EXISTS (SELECT 1 FROM inventory_requisitions r WHERE r.item_id = i.id AND r.solicitante_nome = 'Coordenação Pedagógica' AND r.justificativa = 'Reposição para impressões de avaliações.');

INSERT INTO inventory_requisitions (item_id, warehouse_id, quantidade, setor, solicitante_nome, justificativa, status, created_at)
SELECT i.id, w.id, 3, 'Laboratório de Ciências', 'Prof. Ricardo Nunes', 'Reposição para práticas de laboratório.', 'pendente', DATE_SUB(NOW(), INTERVAL 1 DAY)
FROM inventory_items i CROSS JOIN inventory_warehouses w
WHERE i.codigo = 'LAB-001' AND w.nome = 'Laboratório de Ciências'
AND NOT EXISTS (SELECT 1 FROM inventory_requisitions r WHERE r.item_id = i.id AND r.solicitante_nome = 'Prof. Ricardo Nunes' AND r.justificativa = 'Reposição para práticas de laboratório.');

-- Patrimônio: bens permanentes
INSERT INTO patrimony_assets
(numero_patrimonio, descricao, categoria, numero_serie, marca, modelo, data_aquisicao, valor_aquisicao, nota_fiscal, fornecedor_id, garantia_ate, vida_util_meses, location_id, responsavel_nome, origem, status, observacoes)
SELECT 'PAT-0001', 'Projetor multimídia sala 6º Ano A', 'projetor', 'PJ6A-2026-001', 'Epson', 'PowerLite E20', '2025-02-10', 3290.00, 'NF-TECH-100', s.id, '2027-02-10', 60, l.id, 'Coordenação Pedagógica', 'proprio', 'ativo', 'Instalado em suporte de teto.'
FROM school_locations l
LEFT JOIN inventory_suppliers s ON s.cnpj = '44.444.444/0001-44'
WHERE l.codigo = 'SALA-6A'
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao), location_id = VALUES(location_id), responsavel_nome = VALUES(responsavel_nome), status = VALUES(status), updated_at = NOW();

INSERT INTO patrimony_assets
(numero_patrimonio, descricao, categoria, numero_serie, marca, modelo, data_aquisicao, valor_aquisicao, nota_fiscal, fornecedor_id, garantia_ate, vida_util_meses, location_id, responsavel_nome, origem, status, observacoes)
SELECT 'PAT-0002', 'Notebook administrativo', 'informatica', 'NTB-ADM-002', 'Dell', 'Latitude 3440', '2024-08-15', 4850.00, 'NF-TECH-120', s.id, '2027-08-15', 48, l.id, 'Secretaria Escolar', 'proprio', 'ativo', 'Uso da secretaria para matrículas e relatórios.'
FROM school_locations l
LEFT JOIN inventory_suppliers s ON s.cnpj = '44.444.444/0001-44'
WHERE l.codigo = 'ADM-SEC'
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao), location_id = VALUES(location_id), responsavel_nome = VALUES(responsavel_nome), status = VALUES(status), updated_at = NOW();

INSERT INTO patrimony_assets
(numero_patrimonio, descricao, categoria, numero_serie, marca, modelo, data_aquisicao, valor_aquisicao, nota_fiscal, fornecedor_id, garantia_ate, vida_util_meses, location_id, responsavel_nome, origem, status, observacoes)
SELECT 'PAT-0003', 'Conjunto de carteiras escolares', 'mobiliario', 'MOB-6A-030', 'SchoolMoveis', 'Carteira aluno padrão', '2023-01-20', 7200.00, 'NF-MOB-310', NULL, NULL, 120, l.id, 'Coordenação Pedagógica', 'proprio', 'ativo', '30 carteiras e 30 cadeiras.'
FROM school_locations l
WHERE l.codigo = 'SALA-6A'
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao), location_id = VALUES(location_id), responsavel_nome = VALUES(responsavel_nome), status = VALUES(status), updated_at = NOW();

INSERT INTO patrimony_assets
(numero_patrimonio, descricao, categoria, numero_serie, marca, modelo, data_aquisicao, valor_aquisicao, nota_fiscal, fornecedor_id, garantia_ate, vida_util_meses, location_id, responsavel_nome, origem, status, observacoes)
SELECT 'PAT-0004', 'Microscópio binocular', 'laboratorio', 'MIC-LAB-004', 'Opton', 'TNB-04B', '2022-03-05', 2650.00, 'NF-LAB-084', NULL, NULL, 96, l.id, 'Professor de Ciências', 'proprio', 'ativo', 'Equipamento de bancada.'
FROM school_locations l
WHERE l.codigo = 'LAB-CIEN'
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao), location_id = VALUES(location_id), responsavel_nome = VALUES(responsavel_nome), status = VALUES(status), updated_at = NOW();

INSERT INTO patrimony_assets
(numero_patrimonio, descricao, categoria, numero_serie, marca, modelo, data_aquisicao, valor_aquisicao, nota_fiscal, fornecedor_id, garantia_ate, vida_util_meses, location_id, responsavel_nome, origem, status, observacoes)
SELECT 'PAT-0005', 'Ar-condicionado 24000 BTUs', 'climatizacao', 'AC-LABINF-005', 'LG', 'Dual Inverter', '2024-11-10', 3900.00, 'NF-CLIMA-202', NULL, '2026-11-10', 84, l.id, 'Manutenção Predial', 'proprio', 'manutencao', 'Aguardando visita técnica por ruído.'
FROM school_locations l
WHERE l.codigo = 'LAB-INF'
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao), location_id = VALUES(location_id), responsavel_nome = VALUES(responsavel_nome), status = VALUES(status), updated_at = NOW();

INSERT INTO patrimony_assets
(numero_patrimonio, descricao, categoria, numero_serie, marca, modelo, data_aquisicao, valor_aquisicao, nota_fiscal, fornecedor_id, garantia_ate, vida_util_meses, location_id, responsavel_nome, origem, status, observacoes)
SELECT 'PAT-0006', 'Chromebook cedido para laboratório', 'informatica', 'CHROME-SEB-006', 'Samsung', 'Chromebook 4', '2025-07-01', 2100.00, 'TERMO-COMODATO-01', s.id, '2027-07-01', 48, l.id, 'Professor de Tecnologia', 'comodato', 'ativo', 'Bem em comodato/cedido para uso pedagógico.'
FROM school_locations l
LEFT JOIN inventory_suppliers s ON s.cnpj = '44.444.444/0001-44'
WHERE l.codigo = 'LAB-INF'
ON DUPLICATE KEY UPDATE
    descricao = VALUES(descricao), location_id = VALUES(location_id), responsavel_nome = VALUES(responsavel_nome), origem = VALUES(origem), status = VALUES(status), updated_at = NOW();

-- Movimentações patrimoniais
INSERT INTO patrimony_movements (asset_id, tipo, location_origem_id, location_destino_id, responsavel_origem, responsavel_destino, motivo, documento, realizado_por, created_at)
SELECT a.id, 'transferencia', NULL, l.id, '', a.responsavel_nome, 'Carga inicial e vinculação ao local atual.', 'SEED-PAT-001', NULL, DATE_SUB(NOW(), INTERVAL 15 DAY)
FROM patrimony_assets a
LEFT JOIN school_locations l ON l.id = a.location_id
WHERE a.numero_patrimonio IN ('PAT-0001', 'PAT-0002', 'PAT-0003', 'PAT-0004', 'PAT-0006')
AND NOT EXISTS (SELECT 1 FROM patrimony_movements m WHERE m.asset_id = a.id AND m.documento = 'SEED-PAT-001');

INSERT INTO patrimony_movements (asset_id, tipo, location_origem_id, location_destino_id, responsavel_origem, responsavel_destino, motivo, documento, realizado_por, created_at)
SELECT a.id, 'manutencao_envio', l.id, l.id, a.responsavel_nome, 'Manutenção Predial', 'Ruído excessivo durante funcionamento.', 'OS-CLIMA-2026-01', NULL, DATE_SUB(NOW(), INTERVAL 3 DAY)
FROM patrimony_assets a
LEFT JOIN school_locations l ON l.id = a.location_id
WHERE a.numero_patrimonio = 'PAT-0005'
AND NOT EXISTS (SELECT 1 FROM patrimony_movements m WHERE m.asset_id = a.id AND m.documento = 'OS-CLIMA-2026-01');

-- Conferências de inventário
INSERT INTO patrimony_inventory_checks (asset_id, location_id, status_conferencia, observacoes, conferido_por, created_at)
SELECT a.id, a.location_id, 'ok', 'Conferência demo: bem localizado e em uso.', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM patrimony_assets a
WHERE a.numero_patrimonio IN ('PAT-0001', 'PAT-0002', 'PAT-0003', 'PAT-0004', 'PAT-0006')
AND NOT EXISTS (SELECT 1 FROM patrimony_inventory_checks c WHERE c.asset_id = a.id AND c.observacoes = 'Conferência demo: bem localizado e em uso.');

INSERT INTO patrimony_inventory_checks (asset_id, location_id, status_conferencia, observacoes, conferido_por, created_at)
SELECT a.id, a.location_id, 'avariado', 'Conferência demo: equipamento em manutenção.', NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM patrimony_assets a
WHERE a.numero_patrimonio = 'PAT-0005'
AND NOT EXISTS (SELECT 1 FROM patrimony_inventory_checks c WHERE c.asset_id = a.id AND c.observacoes = 'Conferência demo: equipamento em manutenção.');
