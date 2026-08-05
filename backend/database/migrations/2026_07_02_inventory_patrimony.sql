-- ============================================================
-- Almoxarifado e Patrimônio Escolar — tenant
-- 2026_07_02_inventory_patrimony.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS school_locations (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NULL,
    nome VARCHAR(120) NOT NULL,
    tipo ENUM('sala','laboratorio','cantina','deposito','biblioteca','secretaria','quadra','outro') NOT NULL DEFAULT 'sala',
    bloco VARCHAR(80) NULL,
    andar VARCHAR(30) NULL,
    responsavel_nome VARCHAR(160) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_school_locations_codigo (codigo),
    KEY idx_school_locations_tipo (tipo),
    KEY idx_school_locations_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_suppliers (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(180) NOT NULL,
    cnpj VARCHAR(20) NULL,
    contato VARCHAR(160) NULL,
    telefone VARCHAR(30) NULL,
    email VARCHAR(160) NULL,
    observacoes TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_inventory_suppliers_nome (nome),
    KEY idx_inventory_suppliers_cnpj (cnpj)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_warehouses (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(120) NOT NULL,
    tipo ENUM('central','cantina','laboratorio','limpeza','secretaria','outro') NOT NULL DEFAULT 'central',
    location_id INT NULL,
    responsavel_nome VARCHAR(160) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_inventory_warehouses_location (location_id),
    KEY idx_inventory_warehouses_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(40) NOT NULL,
    descricao VARCHAR(220) NOT NULL,
    unidade_medida VARCHAR(20) NOT NULL DEFAULT 'un',
    categoria ENUM('limpeza','escritorio','didatico','merenda','higiene','laboratorio','outro') NOT NULL DEFAULT 'outro',
    estoque_minimo DECIMAL(12,3) NOT NULL DEFAULT 0,
    estoque_maximo DECIMAL(12,3) NULL,
    ponto_reposicao DECIMAL(12,3) NOT NULL DEFAULT 0,
    custo_medio DECIMAL(12,4) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inventory_items_codigo (codigo),
    KEY idx_inventory_items_categoria (categoria),
    KEY idx_inventory_items_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_lots (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    lote VARCHAR(80) NULL,
    validade DATE NULL,
    quantidade_atual DECIMAL(12,3) NOT NULL DEFAULT 0,
    custo_unitario DECIMAL(12,4) NOT NULL DEFAULT 0,
    entrada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inventory_lots_item_warehouse (item_id, warehouse_id),
    KEY idx_inventory_lots_validade (validade),
    KEY idx_inventory_lots_quantidade (quantidade_atual)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    warehouse_destino_id INT NULL,
    lot_id INT NULL,
    tipo ENUM('entrada','saida','transferencia','ajuste','baixa') NOT NULL,
    quantidade DECIMAL(12,3) NOT NULL,
    custo_unitario DECIMAL(12,4) NULL,
    documento VARCHAR(120) NULL,
    fornecedor_id INT NULL,
    requisicao_id INT NULL,
    setor VARCHAR(120) NULL,
    turma_id INT NULL,
    professor_id INT NULL,
    motivo VARCHAR(255) NOT NULL,
    realizado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_inventory_movements_item (item_id),
    KEY idx_inventory_movements_created (created_at),
    KEY idx_inventory_movements_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_requisitions (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    quantidade DECIMAL(12,3) NOT NULL,
    setor VARCHAR(120) NULL,
    turma_id INT NULL,
    professor_id INT NULL,
    solicitante_nome VARCHAR(160) NOT NULL,
    justificativa VARCHAR(255) NOT NULL,
    status ENUM('pendente','aprovada','atendida','rejeitada','cancelada') NOT NULL DEFAULT 'pendente',
    solicitado_por INT NULL,
    aprovado_por INT NULL,
    aprovado_em DATETIME NULL,
    atendido_por INT NULL,
    atendido_em DATETIME NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_inventory_requisitions_status (status),
    KEY idx_inventory_requisitions_item (item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patrimony_assets (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    numero_patrimonio VARCHAR(60) NOT NULL,
    descricao VARCHAR(220) NOT NULL,
    categoria ENUM('mobiliario','informatica','projetor','climatizacao','laboratorio','veiculo','instrumento','outro') NOT NULL DEFAULT 'outro',
    numero_serie VARCHAR(120) NULL,
    marca VARCHAR(100) NULL,
    modelo VARCHAR(120) NULL,
    data_aquisicao DATE NULL,
    valor_aquisicao DECIMAL(12,2) NOT NULL DEFAULT 0,
    nota_fiscal VARCHAR(120) NULL,
    fornecedor_id INT NULL,
    garantia_ate DATE NULL,
    vida_util_meses INT NOT NULL DEFAULT 60,
    location_id INT NULL,
    responsavel_nome VARCHAR(160) NULL,
    origem ENUM('proprio','comodato','cedido','doado') NOT NULL DEFAULT 'proprio',
    status ENUM('ativo','manutencao','emprestado','baixado','nao_localizado') NOT NULL DEFAULT 'ativo',
    observacoes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_patrimony_assets_numero (numero_patrimonio),
    KEY idx_patrimony_assets_location (location_id),
    KEY idx_patrimony_assets_status (status),
    KEY idx_patrimony_assets_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patrimony_movements (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    tipo ENUM('transferencia','emprestimo','manutencao_envio','manutencao_retorno','inventario','baixa') NOT NULL,
    location_origem_id INT NULL,
    location_destino_id INT NULL,
    responsavel_origem VARCHAR(160) NULL,
    responsavel_destino VARCHAR(160) NULL,
    motivo VARCHAR(255) NOT NULL,
    documento VARCHAR(120) NULL,
    realizado_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_patrimony_movements_asset (asset_id),
    KEY idx_patrimony_movements_tipo (tipo),
    KEY idx_patrimony_movements_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patrimony_inventory_checks (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    location_id INT NULL,
    status_conferencia ENUM('ok','local_errado','nao_localizado','sem_plaqueta','avariado') NOT NULL DEFAULT 'ok',
    observacoes VARCHAR(255) NULL,
    conferido_por INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_patrimony_checks_asset (asset_id),
    KEY idx_patrimony_checks_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
