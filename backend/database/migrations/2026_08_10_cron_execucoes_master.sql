-- MASTER apenas. Histórico estruturado de execuções de cron (ex.: process_ai_jobs).

CREATE TABLE IF NOT EXISTS `cron_execucoes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `script` VARCHAR(64) NOT NULL COMMENT 'Identificador do script, ex.: process_ai_jobs',
  `status` ENUM('rodando','ok','erro','parcial') NOT NULL DEFAULT 'rodando',
  `iniciado_em` DATETIME NOT NULL,
  `finalizado_em` DATETIME NULL,
  `duracao_ms` INT UNSIGNED NULL,
  `escolas_total` INT UNSIGNED NOT NULL DEFAULT 0,
  `escolas_ok` INT UNSIGNED NOT NULL DEFAULT 0,
  `escolas_erro` INT UNSIGNED NOT NULL DEFAULT 0,
  `escolas_puladas` INT UNSIGNED NOT NULL DEFAULT 0,
  `jobs_processados` INT UNSIGNED NOT NULL DEFAULT 0,
  `hostname` VARCHAR(128) NULL,
  `mensagem_erro` TEXT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cron_exec_script_iniciado` (`script`, `iniciado_em`),
  KEY `idx_cron_exec_status_iniciado` (`status`, `iniciado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Uma linha por execução de cron no Master';

CREATE TABLE IF NOT EXISTS `cron_execucoes_escolas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cron_execucao_id` BIGINT UNSIGNED NOT NULL,
  `escola_id` INT UNSIGNED NULL,
  `escola_nome` VARCHAR(255) NULL,
  `status` ENUM('ok','erro','pulada') NOT NULL DEFAULT 'ok',
  `jobs_processados` INT UNSIGNED NOT NULL DEFAULT 0,
  `mensagem_erro` TEXT NULL,
  `iniciado_em` DATETIME NULL,
  `finalizado_em` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cron_exec_esc_execucao` (`cron_execucao_id`),
  KEY `idx_cron_exec_esc_escola` (`escola_id`),
  CONSTRAINT `fk_cron_execucoes_escolas_execucao`
    FOREIGN KEY (`cron_execucao_id`) REFERENCES `cron_execucoes` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Detalhe por escola de uma execução de cron';
