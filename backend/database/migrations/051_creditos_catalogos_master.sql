-- MASTER: catálogos reutilizáveis (tabelas de custo por módulo, pacotes, planos) + vínculo por escola.

CREATE TABLE IF NOT EXISTS `creditos_tabela_custo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(255) NOT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ctc_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Template de precificação por módulo (vincular à escola)';

CREATE TABLE IF NOT EXISTS `creditos_tabela_custo_item` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tabela_id` INT UNSIGNED NOT NULL,
  `modulo_key` VARCHAR(80) NOT NULL,
  `creditos` DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
  `cobra` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = debita crédito neste módulo',
  `nome_exibicao` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ctci_tabela_modulo` (`tabela_id`, `modulo_key`),
  KEY `idx_ctci_tabela` (`tabela_id`),
  CONSTRAINT `fk_ctci_tabela` FOREIGN KEY (`tabela_id`) REFERENCES `creditos_tabela_custo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `creditos_pacotes_catalogo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(255) NOT NULL,
  `creditos` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `valor_centavos` INT UNSIGNED NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cpc_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `creditos_planos_catalogo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(255) NOT NULL,
  `creditos_mensais` INT UNSIGNED NOT NULL DEFAULT 0,
  `valor_mensal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `destino` ENUM('aluno','professor','ambos') NOT NULL DEFAULT 'ambos',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cplc_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `escolas_creditos_vinculo` (
  `escola_id` INT UNSIGNED NOT NULL,
  `tabela_custo_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL = não aplicar template ao salvar vínculos',
  PRIMARY KEY (`escola_id`),
  KEY `idx_ecv_tabela` (`tabela_custo_id`),
  CONSTRAINT `fk_ecv_tabela` FOREIGN KEY (`tabela_custo_id`) REFERENCES `creditos_tabela_custo` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `escolas_creditos_pacotes` (
  `escola_id` INT UNSIGNED NOT NULL,
  `catalogo_pacote_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`escola_id`, `catalogo_pacote_id`),
  KEY `idx_ecp_pacote` (`catalogo_pacote_id`),
  CONSTRAINT `fk_ecp_pacote` FOREIGN KEY (`catalogo_pacote_id`) REFERENCES `creditos_pacotes_catalogo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `escolas_creditos_planos` (
  `escola_id` INT UNSIGNED NOT NULL,
  `catalogo_plano_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`escola_id`, `catalogo_plano_id`),
  KEY `idx_ecpl_plano` (`catalogo_plano_id`),
  CONSTRAINT `fk_ecpl_plano` FOREIGN KEY (`catalogo_plano_id`) REFERENCES `creditos_planos_catalogo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
