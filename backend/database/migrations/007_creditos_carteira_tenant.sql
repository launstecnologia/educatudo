-- Créditos e carteira digital (tenant – banco de cada escola)
-- Executar no banco da escola (tenant). Não executar no banco master.

CREATE TABLE IF NOT EXISTS `carteira_usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` ENUM('aluno','professor') NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `saldo` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_carteira_user` (`user_type`, `user_id`),
  KEY `idx_carteira_user_type_id` (`user_type`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Carteira de créditos por usuário (aluno/professor)';

CREATE TABLE IF NOT EXISTS `carteira_movimentacoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` ENUM('aluno','professor') NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `tipo` ENUM('recarga_mensal','cortesia','compra','consumo','estorno','recarga_plano') NOT NULL,
  `valor` INT NOT NULL COMMENT 'Positivo = entrada, negativo = saída',
  `modulo_key` VARCHAR(64) NULL,
  `referencia_id` VARCHAR(64) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mov_user_created` (`user_type`, `user_id`, `created_at`),
  KEY `idx_mov_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Histórico de movimentações da carteira';

CREATE TABLE IF NOT EXISTS `planos_creditos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(255) NOT NULL,
  `creditos_mensais` INT UNSIGNED NOT NULL,
  `valor_mensal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `destino` ENUM('aluno','professor','ambos') NOT NULL DEFAULT 'ambos',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_planos_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Planos mensais que concedem créditos (assinatura)';

CREATE TABLE IF NOT EXISTS `assinaturas_creditos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_type` ENUM('aluno','professor') NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `plano_id` INT UNSIGNED NOT NULL,
  `inicio_em` DATE NOT NULL,
  `ativa` TINYINT(1) NOT NULL DEFAULT 1,
  `ultima_recarga_em` DATE NULL COMMENT 'Último mês em que foi recarregado',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assinaturas_user` (`user_type`, `user_id`),
  KEY `idx_assinaturas_ativa` (`ativa`),
  KEY `idx_assinaturas_plano` (`plano_id`),
  CONSTRAINT `fk_assinaturas_plano` FOREIGN KEY (`plano_id`) REFERENCES `planos_creditos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Assinaturas ativas de planos de créditos';
