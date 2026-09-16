-- Fechamento oficial por turma × período (máquina de estados canônica).
-- Tenant. Idempotente. Rollback: 2026_09_09_fechamento_periodo_rollback.sql
-- Isolamento é pela conexão PDO; sem coluna escola_id.

SET @db := DATABASE();

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fechamento_periodo');
SET @sql := IF(@has=0,
  "CREATE TABLE `fechamento_periodo` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `turma_id` INT NOT NULL,
    `ano_letivo` SMALLINT UNSIGNED NOT NULL,
    `periodo_tipo` ENUM('bimestre','trimestre','semestre','ano') NOT NULL DEFAULT 'ano',
    `periodo_numero` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 = ano inteiro; 1-4 = etapa',
    `periodo_ref` VARCHAR(32) NOT NULL COMMENT 'chave estável, ex. 2026-B2 ou 2026-ANO',
    `status` ENUM('ABERTO','EM_FECHAMENTO','EM_RECUPERACAO','HOMOLOGADO','RETIFICADO') NOT NULL DEFAULT 'ABERTO',
    `regra_academica_id` INT NULL,
    `homologado_em` DATETIME NULL,
    `homologado_por` INT NULL,
    `retificado_de_id` INT NULL,
    `justificativa` TEXT NULL,
    `vigente` TINYINT(1) NOT NULL DEFAULT 1,
    `vigente_chave` VARCHAR(80) NULL COMMENT 'preenchida só quando vigente=1; UNIQUE permite vários históricos (NULL)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_fechamento_vigente` (`vigente_chave`),
    KEY `idx_fechamento_turma_periodo` (`turma_id`, `ano_letivo`, `periodo_tipo`, `periodo_numero`, `vigente`),
    KEY `idx_fechamento_status` (`status`, `ano_letivo`),
    KEY `idx_fechamento_retificado_de` (`retificado_de_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fechamento_periodo_historico');
SET @sql := IF(@has=0,
  "CREATE TABLE `fechamento_periodo_historico` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `fechamento_id` INT NOT NULL,
    `status_anterior` VARCHAR(20) NULL,
    `status_novo` VARCHAR(20) NOT NULL,
    `justificativa` TEXT NULL,
    `usuario_id` INT NULL,
    `payload_json` MEDIUMTEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fechamento_hist_fechamento` (`fechamento_id`, `id`),
    CONSTRAINT `fk_fechamento_hist_periodo` FOREIGN KEY (`fechamento_id`) REFERENCES `fechamento_periodo` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_fech := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fechamento_periodo');
SET @has_turmas := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas');
SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fechamento_periodo' AND CONSTRAINT_NAME='fk_fechamento_turma' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk=0 AND @has_fech>0 AND @has_turmas>0,
  "ALTER TABLE `fechamento_periodo` ADD CONSTRAINT `fk_fechamento_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_res := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_academico');
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_academico' AND COLUMN_NAME='fechamento_periodo_id');
SET @sql := IF(@has_res>0 AND @col=0,
  "ALTER TABLE `resultado_academico` ADD COLUMN `fechamento_periodo_id` INT NULL DEFAULT NULL AFTER `id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='resultado_academico' AND INDEX_NAME='idx_resultado_fechamento_periodo');
SET @sql := IF(@has_res>0 AND @idx=0,
  "ALTER TABLE `resultado_academico` ADD KEY `idx_resultado_fechamento_periodo` (`fechamento_periodo_id`)",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
