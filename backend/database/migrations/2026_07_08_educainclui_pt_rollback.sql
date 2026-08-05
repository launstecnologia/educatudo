-- Rollback: EducaInclui rename de tabelas/colunas para português

ALTER TABLE `versoes_adaptadas_logs`
  DROP INDEX `idx_log_versao`,
  DROP INDEX `idx_log_aluno`,
  CHANGE COLUMN `versao_adaptada_id` `assessment_version_id` BIGINT NULL,
  CHANGE COLUMN `mascara_id` `accommodation_id` BIGINT NULL,
  CHANGE COLUMN `prova_id` `assessment_id` INT NULL,
  CHANGE COLUMN `acao` `action` VARCHAR(60) NOT NULL,
  CHANGE COLUMN `detalhes_json` `details_json` JSON NULL,
  ADD INDEX `idx_assesslog_version` (`assessment_version_id`),
  ADD INDEX `idx_assesslog_aluno` (`aluno_id`);

ALTER TABLE `versoes_adaptadas`
  DROP INDEX `idx_versao_prova_aluno`,
  DROP INDEX `idx_versao_status`,
  CHANGE COLUMN `prova_id` `assessment_id` INT NOT NULL,
  CHANGE COLUMN `mascara_id` `accommodation_id` BIGINT NOT NULL,
  CHANGE COLUMN `tipo_versao` `version_type` ENUM('acesso','significativa') NOT NULL DEFAULT 'acesso',
  CHANGE COLUMN `hash_prova_origem` `source_assessment_hash` CHAR(64) NOT NULL,
  CHANGE COLUMN `regras_snapshot_json` `rules_snapshot_json` JSON NOT NULL,
  CHANGE COLUMN `status_aprovacao` `approval_status` ENUM('pendente','aprovada_professor','aprovada_aee','aprovada','invalidada_drift','rejeitada') NOT NULL DEFAULT 'pendente',
  CHANGE COLUMN `aprovado_por_professor` `approved_by_professor` INT NULL,
  CHANGE COLUMN `aprovado_por_aee` `approved_by_aee` INT NULL,
  CHANGE COLUMN `gerado_de_versao_id` `generated_from_version_id` BIGINT NULL,
  ADD INDEX `idx_assessver_assessment_aluno` (`assessment_id`, `aluno_id`),
  ADD INDEX `idx_assessver_status` (`approval_status`);

ALTER TABLE `regras_mascara`
  DROP FOREIGN KEY `fk_regra_mascara`,
  DROP INDEX `uq_regra_mascara_chave`,
  CHANGE COLUMN `mascara_id` `accommodation_id` BIGINT NOT NULL,
  CHANGE COLUMN `chave_regra` `rule_key` VARCHAR(60) NOT NULL,
  CHANGE COLUMN `valor_regra` `rule_value` VARCHAR(255) NULL,
  CHANGE COLUMN `precedencia` `precedence` INT NOT NULL DEFAULT 0,
  ADD UNIQUE INDEX `uq_accrule_key` (`accommodation_id`, `rule_key`),
  ADD CONSTRAINT `fk_accrule_accommodation` FOREIGN KEY (`accommodation_id`)
    REFERENCES `mascaras_alunos` (`id`) ON DELETE CASCADE;

ALTER TABLE `laudos_alunos`
  DROP FOREIGN KEY `fk_laudo_mascara`,
  DROP INDEX `idx_laudo_mascara`,
  CHANGE COLUMN `mascara_id` `accommodation_id` BIGINT NOT NULL,
  CHANGE COLUMN `caminho_arquivo` `file_path` VARCHAR(500) NOT NULL,
  CHANGE COLUMN `hash_arquivo` `file_hash` CHAR(64) NOT NULL,
  CHANGE COLUMN `tipo_mime` `mime_type` VARCHAR(120) NOT NULL,
  CHANGE COLUMN `nome_original` `original_name` VARCHAR(255) NULL,
  CHANGE COLUMN `algoritmo_cripto` `enc_algo` VARCHAR(40) NOT NULL DEFAULT 'AES-256-GCM',
  CHANGE COLUMN `enviado_por` `uploaded_by` INT NULL,
  ADD INDEX `idx_accdoc_accommodation` (`accommodation_id`),
  ADD CONSTRAINT `fk_accdoc_accommodation` FOREIGN KEY (`accommodation_id`)
    REFERENCES `mascaras_alunos` (`id`) ON DELETE CASCADE;

ALTER TABLE `mascaras_alunos`
  DROP INDEX `idx_mascara_aluno_status`,
  CHANGE COLUMN `data_inicio` `start_date` DATE NULL,
  CHANGE COLUMN `data_fim` `end_date` DATE NULL,
  CHANGE COLUMN `base_legal` `legal_basis` VARCHAR(120) NULL,
  CHANGE COLUMN `ref_consentimento` `consent_ref` VARCHAR(160) NULL,
  CHANGE COLUMN `observacoes` `notes` TEXT NULL,
  CHANGE COLUMN `criado_por` `created_by` INT NULL,
  CHANGE COLUMN `aprovado_por` `approved_by` INT NULL,
  CHANGE COLUMN `aprovado_em` `approved_at` DATETIME NULL,
  ADD INDEX `idx_accommodation_aluno_status` (`aluno_id`, `status`);

RENAME TABLE `versoes_adaptadas_logs` TO `assessment_version_logs`;
RENAME TABLE `versoes_adaptadas` TO `assessment_versions`;
RENAME TABLE `regras_mascara` TO `accommodation_rules`;
RENAME TABLE `laudos_alunos` TO `accommodation_documents`;
RENAME TABLE `mascaras_alunos` TO `student_accommodations`;
