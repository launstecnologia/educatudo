-- Unifica o público do mural (aluno ou professor, vários ou um) e da comunicação
-- com os pais (série, curso, YouTube e push opcional). O recado do mural também
-- entra no sino via notificacoes.mural_recado_id.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_31_comunicacao_unificada_rollback.sql

SET @db := DATABASE();

-- mural_recados.publico
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mural_recados' AND COLUMN_NAME = 'publico'
);
SET @sql := IF(@col = 0,
  "ALTER TABLE `mural_recados` ADD COLUMN `publico` ENUM('alunos','professores') NOT NULL DEFAULT 'alunos' AFTER `enviar_para_todos`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mural_recados' AND COLUMN_NAME = 'aluno_id'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE `mural_recados` ADD COLUMN `aluno_id` INT UNSIGNED NULL DEFAULT NULL AFTER `publico`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mural_recados' AND COLUMN_NAME = 'professor_id'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE `mural_recados` ADD COLUMN `professor_id` INT UNSIGNED NULL DEFAULT NULL AFTER `aluno_id`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Confirmação de leitura do professor (aluno_id passa a aceitar nulo)
SET @nullable := (
  SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mural_recados_vistos' AND COLUMN_NAME = 'aluno_id'
);
SET @sql := IF(@nullable = 'NO',
  'ALTER TABLE `mural_recados_vistos` MODIFY COLUMN `aluno_id` INT UNSIGNED NULL DEFAULT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mural_recados_vistos' AND COLUMN_NAME = 'professor_id'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE `mural_recados_vistos` ADD COLUMN `professor_id` INT UNSIGNED NULL DEFAULT NULL AFTER `aluno_id`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mural_recados_vistos' AND INDEX_NAME = 'uniq_mural_professor'
);
SET @sql := IF(@idx = 0,
  'ALTER TABLE `mural_recados_vistos` ADD UNIQUE KEY `uniq_mural_professor` (`mural_recado_id`, `professor_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Sino aponta para o recado do mural
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'notificacoes' AND COLUMN_NAME = 'mural_recado_id'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE `notificacoes` ADD COLUMN `mural_recado_id` INT UNSIGNED NULL DEFAULT NULL AFTER `is_update`, ADD KEY `idx_notificacoes_mural` (`mural_recado_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Comunicação: série, curso, vídeo e push opcional
SET @tipo := (
  SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'school_communications' AND COLUMN_NAME = 'publico'
);
SET @sql := IF(@tipo IS NOT NULL AND @tipo NOT LIKE '%series%',
  "ALTER TABLE `school_communications` MODIFY COLUMN `publico` ENUM('todos','turmas','alunos','series','cursos') NOT NULL",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'school_communications' AND COLUMN_NAME = 'video_url'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE `school_communications` ADD COLUMN `video_url` VARCHAR(500) NULL DEFAULT NULL AFTER `conteudo`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'school_communications' AND COLUMN_NAME = 'enviar_push'
);
SET @sql := IF(@col = 0,
  'ALTER TABLE `school_communications` ADD COLUMN `enviar_push` TINYINT(1) NOT NULL DEFAULT 0 AFTER `permite_resposta`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `school_communication_series` (
  `communication_id` BIGINT UNSIGNED NOT NULL,
  `serie_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`communication_id`, `serie_id`),
  KEY `idx_school_communication_serie` (`serie_id`, `communication_id`),
  CONSTRAINT `fk_school_communication_serie` FOREIGN KEY (`communication_id`) REFERENCES `school_communications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `school_communication_cursos` (
  `communication_id` BIGINT UNSIGNED NOT NULL,
  `curso_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`communication_id`, `curso_id`),
  KEY `idx_school_communication_curso` (`curso_id`, `communication_id`),
  CONSTRAINT `fk_school_communication_curso` FOREIGN KEY (`communication_id`) REFERENCES `school_communications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
