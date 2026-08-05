-- TENANT (banco de cada escola). Não contém "master" no nome.
-- Pacotes à venda e compras (integração Asaas preenche asaas_payment_id após checkout).

CREATE TABLE IF NOT EXISTS `pacotes_creditos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(255) NOT NULL,
  `creditos` DECIMAL(14,4) NOT NULL DEFAULT 0.0000 COMMENT 'Quantidade de créditos do pacote',
  `valor_centavos` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Preço em centavos (BRL)',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pacotes_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Pacotes de créditos B2C';

CREATE TABLE IF NOT EXISTS `compras_creditos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` ENUM('aluno','professor') NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `pacote_id` INT UNSIGNED NOT NULL,
  `valor_centavos` INT UNSIGNED NOT NULL,
  `status` ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  `asaas_payment_id` VARCHAR(64) NULL DEFAULT NULL,
  `checkout_url` VARCHAR(1024) NULL DEFAULT NULL,
  `email_notified_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_compras_asaas_payment` (`asaas_payment_id`),
  KEY `idx_compras_user_status` (`user_type`, `user_id`, `status`),
  KEY `idx_compras_pending_created` (`status`, `created_at`),
  CONSTRAINT `fk_compras_pacote` FOREIGN KEY (`pacote_id`) REFERENCES `pacotes_creditos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Compras de pacotes; pagamento via Asaas (Master)';

-- Tipo usado em CreditosService::aplicarRecargaInicialSeAplicavel
ALTER TABLE `carteira_movimentacoes`
  MODIFY COLUMN `tipo` ENUM(
    'recarga_mensal',
    'cortesia',
    'compra',
    'consumo',
    'estorno',
    'recarga_plano',
    'recarga_inicial'
  ) NOT NULL;
