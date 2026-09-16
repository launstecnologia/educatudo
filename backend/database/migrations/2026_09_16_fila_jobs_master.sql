-- MASTER apenas. Fila de jobs longos do painel Master (ex.: clonar escola).
-- Idempotente.

CREATE TABLE IF NOT EXISTS `fila_jobs_master` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo` VARCHAR(64) NOT NULL COMMENT 'Ex.: clonar_escola',
  `status` ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
  `payload` LONGTEXT NOT NULL COMMENT 'JSON (senhas só cifradas)',
  `resultado` LONGTEXT NULL COMMENT 'JSON de progresso/resultado',
  `mensagem_erro` TEXT NULL,
  `tentativas` INT UNSIGNED NOT NULL DEFAULT 0,
  `escola_origem_id` INT UNSIGNED NULL,
  `escola_destino_id` INT UNSIGNED NULL,
  `criado_por` INT UNSIGNED NULL COMMENT 'usuarios_master.id',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `started_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fjm_status_id` (`status`, `id`),
  KEY `idx_fjm_tipo_status` (`tipo`, `status`),
  KEY `idx_fjm_destino` (`escola_destino_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Fila de jobs assíncronos do Master (clonagem de escola, etc.)';
