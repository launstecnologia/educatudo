-- Pacotes de créditos e compras (tenant)
-- Executar no banco da escola (tenant).

CREATE TABLE IF NOT EXISTS `pacotes_creditos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `creditos` INT UNSIGNED NOT NULL,
  `valor_centavos` INT UNSIGNED NOT NULL DEFAULT 0,
  `nome` VARCHAR(255) NULL COMMENT 'Ex: Pacote 10 créditos',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pacotes_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Pacotes de créditos à venda (preço em centavos)';

CREATE TABLE IF NOT EXISTS `compras_creditos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` ENUM('aluno','professor') NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `pacote_id` INT UNSIGNED NOT NULL,
  `valor_centavos` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
  `gateway_id` VARCHAR(128) NULL COMMENT 'ID do pedido no gateway de pagamento',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_compras_user` (`user_type`, `user_id`),
  KEY `idx_compras_status` (`status`),
  KEY `idx_compras_gateway` (`gateway_id`),
  CONSTRAINT `fk_compras_pacote` FOREIGN KEY (`pacote_id`) REFERENCES `pacotes_creditos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Registro de compras de créditos (pendente até confirmação do gateway)';

INSERT IGNORE INTO `pacotes_creditos` (`id`, `creditos`, `valor_centavos`, `nome`, `ativo`) VALUES
(1, 10, 500, 'Pacote 10 créditos', 1),
(2, 50, 2000, 'Pacote 50 créditos', 1),
(3, 100, 3500, 'Pacote 100 créditos', 1);
