-- EducaInclui — rename de tabelas/colunas para português (segunda etapa da padronização)
-- Migration de TENANT. Isolamento por conexão PDO (sem escola_id).
-- Complementa o rename de código já feito em app/Controllers|Models|Services/EducaInclui.
-- Ver .claude/docs/nomenclatura.md

RENAME TABLE `student_accommodations` TO `mascaras_alunos`;
RENAME TABLE `accommodation_documents` TO `laudos_alunos`;
RENAME TABLE `accommodation_rules` TO `regras_mascara`;
RENAME TABLE `assessment_versions` TO `versoes_adaptadas`;
RENAME TABLE `assessment_version_logs` TO `versoes_adaptadas_logs`;

ALTER TABLE `mascaras_alunos`
  DROP INDEX `idx_accommodation_aluno_status`,
  CHANGE COLUMN `start_date` `data_inicio` DATE NULL,
  CHANGE COLUMN `end_date` `data_fim` DATE NULL,
  CHANGE COLUMN `legal_basis` `base_legal` VARCHAR(120) NULL,
  CHANGE COLUMN `consent_ref` `ref_consentimento` VARCHAR(160) NULL,
  CHANGE COLUMN `notes` `observacoes` TEXT NULL,
  CHANGE COLUMN `created_by` `criado_por` INT NULL,
  CHANGE COLUMN `approved_by` `aprovado_por` INT NULL,
  CHANGE COLUMN `approved_at` `aprovado_em` DATETIME NULL,
  ADD INDEX `idx_mascara_aluno_status` (`aluno_id`, `status`);

ALTER TABLE `laudos_alunos`
  DROP FOREIGN KEY `fk_accdoc_accommodation`,
  DROP INDEX `idx_accdoc_accommodation`,
  CHANGE COLUMN `accommodation_id` `mascara_id` BIGINT NOT NULL,
  CHANGE COLUMN `file_path` `caminho_arquivo` VARCHAR(500) NOT NULL,
  CHANGE COLUMN `file_hash` `hash_arquivo` CHAR(64) NOT NULL,
  CHANGE COLUMN `mime_type` `tipo_mime` VARCHAR(120) NOT NULL,
  CHANGE COLUMN `original_name` `nome_original` VARCHAR(255) NULL,
  CHANGE COLUMN `enc_algo` `algoritmo_cripto` VARCHAR(40) NOT NULL DEFAULT 'AES-256-GCM',
  CHANGE COLUMN `uploaded_by` `enviado_por` INT NULL,
  ADD INDEX `idx_laudo_mascara` (`mascara_id`),
  ADD CONSTRAINT `fk_laudo_mascara` FOREIGN KEY (`mascara_id`)
    REFERENCES `mascaras_alunos` (`id`) ON DELETE CASCADE;

ALTER TABLE `regras_mascara`
  DROP FOREIGN KEY `fk_accrule_accommodation`,
  DROP INDEX `uq_accrule_key`,
  CHANGE COLUMN `accommodation_id` `mascara_id` BIGINT NOT NULL,
  CHANGE COLUMN `rule_key` `chave_regra` VARCHAR(60) NOT NULL,
  CHANGE COLUMN `rule_value` `valor_regra` VARCHAR(255) NULL,
  CHANGE COLUMN `precedence` `precedencia` INT NOT NULL DEFAULT 0,
  ADD UNIQUE INDEX `uq_regra_mascara_chave` (`mascara_id`, `chave_regra`),
  ADD CONSTRAINT `fk_regra_mascara` FOREIGN KEY (`mascara_id`)
    REFERENCES `mascaras_alunos` (`id`) ON DELETE CASCADE;

ALTER TABLE `versoes_adaptadas`
  DROP INDEX `idx_assessver_assessment_aluno`,
  DROP INDEX `idx_assessver_status`,
  CHANGE COLUMN `assessment_id` `prova_id` INT NOT NULL,
  CHANGE COLUMN `accommodation_id` `mascara_id` BIGINT NOT NULL,
  CHANGE COLUMN `version_type` `tipo_versao` ENUM('acesso','significativa') NOT NULL DEFAULT 'acesso',
  CHANGE COLUMN `source_assessment_hash` `hash_prova_origem` CHAR(64) NOT NULL,
  CHANGE COLUMN `rules_snapshot_json` `regras_snapshot_json` JSON NOT NULL,
  CHANGE COLUMN `approval_status` `status_aprovacao` ENUM('pendente','aprovada_professor','aprovada_aee','aprovada','invalidada_drift','rejeitada') NOT NULL DEFAULT 'pendente',
  CHANGE COLUMN `approved_by_professor` `aprovado_por_professor` INT NULL,
  CHANGE COLUMN `approved_by_aee` `aprovado_por_aee` INT NULL,
  CHANGE COLUMN `generated_from_version_id` `gerado_de_versao_id` BIGINT NULL,
  ADD INDEX `idx_versao_prova_aluno` (`prova_id`, `aluno_id`),
  ADD INDEX `idx_versao_status` (`status_aprovacao`);

ALTER TABLE `versoes_adaptadas_logs`
  DROP INDEX `idx_assesslog_version`,
  DROP INDEX `idx_assesslog_aluno`,
  CHANGE COLUMN `assessment_version_id` `versao_adaptada_id` BIGINT NULL,
  CHANGE COLUMN `accommodation_id` `mascara_id` BIGINT NULL,
  CHANGE COLUMN `assessment_id` `prova_id` INT NULL,
  CHANGE COLUMN `action` `acao` VARCHAR(60) NOT NULL,
  CHANGE COLUMN `details_json` `detalhes_json` JSON NULL,
  ADD INDEX `idx_log_versao` (`versao_adaptada_id`),
  ADD INDEX `idx_log_aluno` (`aluno_id`);
