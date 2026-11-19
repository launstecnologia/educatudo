-- EducaSync: cenários, slots, demandas, versões e alocações da grade horária gerada por solver.
-- A publicação grava em grade_horaria (sem alterar o schema dela). sala_id fica só no rascunho.
-- Tenant. Idempotente. Rollback: 2026_11_12_educasync_rollback.sql

SET @db := DATABASE();

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='educasync_cenarios');
SET @sql := IF(@has=0,
  "CREATE TABLE `educasync_cenarios` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nome` VARCHAR(150) NOT NULL,
    `ano_letivo` INT NOT NULL,
    `periodo` ENUM('manha','tarde','noite') NOT NULL DEFAULT 'manha',
    `status` ENUM('rascunho','ativo','arquivado') NOT NULL DEFAULT 'rascunho',
    `pesos_json` JSON NULL,
    `max_time_s` INT UNSIGNED NOT NULL DEFAULT 60,
    `created_by` INT UNSIGNED NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_educasync_cenarios_ano` (`ano_letivo`, `status`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='educasync_slots');
SET @sql := IF(@has=0,
  "CREATE TABLE `educasync_slots` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cenario_id` INT UNSIGNED NOT NULL,
    `dia_semana` TINYINT UNSIGNED NOT NULL COMMENT '1=Segunda ... 7=Domingo',
    `ordem` TINYINT UNSIGNED NOT NULL,
    `horario_de` TIME NOT NULL,
    `horario_ate` TIME NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_educasync_slot` (`cenario_id`, `dia_semana`, `ordem`),
    CONSTRAINT `fk_educasync_slots_cenario` FOREIGN KEY (`cenario_id`) REFERENCES `educasync_cenarios` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='educasync_demandas');
SET @sql := IF(@has=0,
  "CREATE TABLE `educasync_demandas` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cenario_id` INT UNSIGNED NOT NULL,
    `turma_id` INT NOT NULL,
    `materia_id` INT NOT NULL,
    `professor_id` INT NULL,
    `aulas_semana` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `sala_id` INT NULL,
    `origem` ENUM('matriz','manual') NOT NULL DEFAULT 'manual',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_educasync_demanda` (`cenario_id`, `turma_id`, `materia_id`),
    KEY `idx_educasync_demandas_prof` (`cenario_id`, `professor_id`),
    CONSTRAINT `fk_educasync_demandas_cenario` FOREIGN KEY (`cenario_id`) REFERENCES `educasync_cenarios` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='educasync_disponibilidades');
SET @sql := IF(@has=0,
  "CREATE TABLE `educasync_disponibilidades` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cenario_id` INT UNSIGNED NOT NULL,
    `tipo` ENUM('professor','sala') NOT NULL,
    `ref_id` INT NOT NULL,
    `dia_semana` TINYINT UNSIGNED NOT NULL,
    `slot_id` INT UNSIGNED NOT NULL,
    `livre` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_educasync_disp` (`cenario_id`, `tipo`, `ref_id`, `slot_id`),
    KEY `idx_educasync_disp_slot` (`slot_id`),
    CONSTRAINT `fk_educasync_disp_cenario` FOREIGN KEY (`cenario_id`) REFERENCES `educasync_cenarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_educasync_disp_slot` FOREIGN KEY (`slot_id`) REFERENCES `educasync_slots` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='educasync_restricoes');
SET @sql := IF(@has=0,
  "CREATE TABLE `educasync_restricoes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cenario_id` INT UNSIGNED NOT NULL,
    `tipo` VARCHAR(60) NOT NULL,
    `escopo` VARCHAR(60) NOT NULL DEFAULT 'global',
    `parametros_json` JSON NULL,
    `hard` TINYINT(1) NOT NULL DEFAULT 0,
    `peso` INT NOT NULL DEFAULT 0,
    `origem` ENUM('manual','assistente') NOT NULL DEFAULT 'manual',
    `ativa` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_educasync_restricoes_cenario` (`cenario_id`, `ativa`),
    CONSTRAINT `fk_educasync_restricoes_cenario` FOREIGN KEY (`cenario_id`) REFERENCES `educasync_cenarios` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='educasync_versoes');
SET @sql := IF(@has=0,
  "CREATE TABLE `educasync_versoes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cenario_id` INT UNSIGNED NOT NULL,
    `status` ENUM('gerada','selecionada','publicada','descartada') NOT NULL DEFAULT 'gerada',
    `metricas_json` JSON NULL,
    `solver_status` VARCHAR(20) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_educasync_versoes_cenario` (`cenario_id`, `created_at`),
    CONSTRAINT `fk_educasync_versoes_cenario` FOREIGN KEY (`cenario_id`) REFERENCES `educasync_cenarios` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='educasync_alocacoes');
SET @sql := IF(@has=0,
  "CREATE TABLE `educasync_alocacoes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `versao_id` INT UNSIGNED NOT NULL,
    `demanda_id` INT UNSIGNED NOT NULL,
    `slot_id` INT UNSIGNED NOT NULL,
    `professor_id` INT NULL,
    `sala_id` INT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_educasync_aloc_versao` (`versao_id`),
    KEY `idx_educasync_aloc_demanda` (`demanda_id`),
    KEY `idx_educasync_aloc_slot` (`slot_id`),
    CONSTRAINT `fk_educasync_aloc_versao` FOREIGN KEY (`versao_id`) REFERENCES `educasync_versoes` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_educasync_aloc_demanda` FOREIGN KEY (`demanda_id`) REFERENCES `educasync_demandas` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_educasync_aloc_slot` FOREIGN KEY (`slot_id`) REFERENCES `educasync_slots` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='educasync_execucoes');
SET @sql := IF(@has=0,
  "CREATE TABLE `educasync_execucoes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cenario_id` INT UNSIGNED NOT NULL,
    `versao_id` INT UNSIGNED NULL,
    `ai_job_id` BIGINT UNSIGNED NULL,
    `usuario_id` INT UNSIGNED NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pendente' COMMENT 'pendente|executando|concluida|inviavel|falhou',
    `solver_status` VARCHAR(20) NULL,
    `objetivo` DOUBLE NULL,
    `best_bound` DOUBLE NULL,
    `tempo_ms` INT UNSIGNED NULL,
    `parametros_json` JSON NULL,
    `log_resumo` TEXT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_educasync_exec_cenario` (`cenario_id`, `created_at`),
    KEY `idx_educasync_exec_job` (`ai_job_id`),
    CONSTRAINT `fk_educasync_exec_cenario` FOREIGN KEY (`cenario_id`) REFERENCES `educasync_cenarios` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_educasync_exec_versao` FOREIGN KEY (`versao_id`) REFERENCES `educasync_versoes` (`id`) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Stub para o assistente em linguagem natural (entrega futura).
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='educasync_comandos_ia');
SET @sql := IF(@has=0,
  "CREATE TABLE `educasync_comandos_ia` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cenario_id` INT UNSIGNED NOT NULL,
    `usuario_id` INT UNSIGNED NULL,
    `prompt` TEXT NOT NULL,
    `proposta_json` JSON NULL,
    `confirmado` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_educasync_cmd_cenario` (`cenario_id`, `created_at`),
    CONSTRAINT `fk_educasync_cmd_cenario` FOREIGN KEY (`cenario_id`) REFERENCES `educasync_cenarios` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='educasync_publicacoes');
SET @sql := IF(@has=0,
  "CREATE TABLE `educasync_publicacoes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `versao_id` INT UNSIGNED NOT NULL,
    `usuario_id` INT UNSIGNED NULL,
    `modo` VARCHAR(30) NOT NULL DEFAULT 'substituir',
    `diff_json` JSON NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_educasync_pub_versao` (`versao_id`),
    CONSTRAINT `fk_educasync_pub_versao` FOREIGN KEY (`versao_id`) REFERENCES `educasync_versoes` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
