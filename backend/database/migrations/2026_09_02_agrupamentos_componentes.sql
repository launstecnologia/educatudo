-- Cadastro reutilizável de agrupamento de componentes (linha única no boletim).
-- Tenant. Idempotente. Rollback: 2026_09_02_agrupamentos_componentes_rollback.sql

SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS `agrupamentos_componentes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(150) NOT NULL,
  `modo` ENUM('media','soma') NOT NULL DEFAULT 'media',
  `aplicar_em` ENUM('boletim','ambos') NOT NULL DEFAULT 'boletim',
  `divisor` DECIMAL(8,2) NULL DEFAULT NULL,
  `materia_rotulo_id` INT NULL DEFAULT NULL COMMENT 'Componente pai (rótulo de área), opcional',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_agrupamentos_componentes_ativo` (`ativo`),
  KEY `idx_agrupamentos_componentes_rotulo` (`materia_rotulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `agrupamentos_componentes_itens` (
  `agrupamento_id` INT UNSIGNED NOT NULL,
  `materia_id` INT NOT NULL,
  PRIMARY KEY (`agrupamento_id`, `materia_id`),
  KEY `idx_agrupamentos_componentes_itens_materia` (`materia_id`),
  CONSTRAINT `fk_aci_agrupamento`
    FOREIGN KEY (`agrupamento_id`) REFERENCES `agrupamentos_componentes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
