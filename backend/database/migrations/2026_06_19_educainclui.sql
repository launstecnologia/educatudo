-- EducaInclui — Provas Adaptativas por Laudo (Fase 1)
-- Migration de TENANT. Isolamento por conexão PDO (sem escola_id).
-- Ver docs/EDUCAINCLUI_PROVAS_ADAPTATIVAS.md

CREATE TABLE IF NOT EXISTS `student_accommodations` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `aluno_id` INT NOT NULL,
  `status` ENUM('rascunho','ativa','suspensa','encerrada') NOT NULL DEFAULT 'rascunho',
  `tipo_adaptacao` ENUM('acesso','significativa') NOT NULL DEFAULT 'acesso',
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `legal_basis` VARCHAR(120) NULL,
  `consent_ref` VARCHAR(160) NULL,
  `notes` TEXT NULL,
  `created_by` INT NULL,
  `approved_by` INT NULL,
  `approved_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_accommodation_aluno_status` (`aluno_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accommodation_documents` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `accommodation_id` BIGINT NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_hash` CHAR(64) NOT NULL,
  `mime_type` VARCHAR(120) NOT NULL,
  `original_name` VARCHAR(255) NULL,
  `enc_algo` VARCHAR(40) NOT NULL DEFAULT 'AES-256-GCM',
  `uploaded_by` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_accdoc_accommodation` (`accommodation_id`),
  CONSTRAINT `fk_accdoc_accommodation` FOREIGN KEY (`accommodation_id`)
    REFERENCES `student_accommodations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accommodation_rules` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `accommodation_id` BIGINT NOT NULL,
  `rule_key` VARCHAR(60) NOT NULL,
  `rule_value` VARCHAR(255) NULL,
  `precedence` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accrule_key` (`accommodation_id`, `rule_key`),
  CONSTRAINT `fk_accrule_accommodation` FOREIGN KEY (`accommodation_id`)
    REFERENCES `student_accommodations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assessment_versions` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `assessment_id` INT NOT NULL,
  `aluno_id` INT NOT NULL,
  `accommodation_id` BIGINT NOT NULL,
  `version_type` ENUM('acesso','significativa') NOT NULL DEFAULT 'acesso',
  `source_assessment_hash` CHAR(64) NOT NULL,
  `adapted_prova_id` INT NULL,
  `rules_snapshot_json` JSON NOT NULL,
  `approval_status` ENUM('pendente','aprovada_professor','aprovada_aee','aprovada','invalidada_drift','rejeitada') NOT NULL DEFAULT 'pendente',
  `approved_by_professor` INT NULL,
  `approved_by_aee` INT NULL,
  `generated_from_version_id` BIGINT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assessver_assessment_aluno` (`assessment_id`, `aluno_id`),
  KEY `idx_assessver_status` (`approval_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `assessment_version_logs` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `assessment_version_id` BIGINT NULL,
  `accommodation_id` BIGINT NULL,
  `aluno_id` INT NULL,
  `assessment_id` INT NULL,
  `action` VARCHAR(60) NOT NULL,
  `user_id` INT NULL,
  `ip` VARCHAR(45) NULL,
  `details_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_assesslog_version` (`assessment_version_id`),
  KEY `idx_assesslog_aluno` (`aluno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
