-- Ofícios da secretaria (numeração anual). Emissão em PDF no papel timbrado.
-- Rollback: 2026_08_31_secretaria_oficios_rollback.sql

CREATE TABLE IF NOT EXISTS `secretaria_oficios` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `numero` INT UNSIGNED NULL DEFAULT NULL,
  `ano` SMALLINT UNSIGNED NOT NULL,
  `data_oficio` DATE NOT NULL,
  `destinatario` VARCHAR(255) NOT NULL,
  `cargo_destinatario` VARCHAR(255) NULL DEFAULT NULL,
  `instituicao` VARCHAR(255) NULL DEFAULT NULL,
  `assunto` VARCHAR(255) NOT NULL,
  `corpo` TEXT NOT NULL,
  `aluno_id` INT NULL DEFAULT NULL,
  `turma_id` INT NULL DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'rascunho',
  `criado_por` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_secretaria_oficios_numero_ano` (`numero`, `ano`),
  KEY `idx_secretaria_oficios_ano` (`ano`),
  KEY `idx_secretaria_oficios_status` (`status`),
  KEY `idx_secretaria_oficios_aluno` (`aluno_id`),
  KEY `idx_secretaria_oficios_turma` (`turma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
