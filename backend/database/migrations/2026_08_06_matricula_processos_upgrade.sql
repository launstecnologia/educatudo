-- Upgrade do processo de matrícula: enrollment → matricula_processos + satélites.
-- Idempotente. Sem materialização financeira/Asaas nesta etapa.
-- Rollback: 2026_08_06_matricula_processos_upgrade_rollback.sql

SET @db := DATABASE();

-- ── 1) Rename tabelas legado (só se ainda existirem com nome EN) ─────────────

SET @has_enr := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'enrollment'
);
SET @has_mp := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matricula_processos'
);
SET @sql := IF(@has_enr > 0 AND @has_mp = 0,
  'RENAME TABLE `enrollment` TO `matricula_processos`',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_audit_old := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'enrollment_audit'
);
SET @has_audit_new := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matricula_processos_auditorias'
);
SET @sql := IF(@has_audit_old > 0 AND @has_audit_new = 0,
  'RENAME TABLE `enrollment_audit` TO `matricula_processos_auditorias`',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_score_old := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'enrollment_score'
);
SET @has_score_new := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matricula_processos_scores'
);
SET @sql := IF(@has_score_old > 0 AND @has_score_new = 0,
  'RENAME TABLE `enrollment_score` TO `matricula_processos_scores`',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 2) Criar matricula_processos se não existir (tenant sem migration antiga) ─

SET @has_mp := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matricula_processos'
);
SET @sql := IF(@has_mp > 0, 'SELECT 1',
"CREATE TABLE `matricula_processos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tipo` ENUM('nova','rematricula','transferencia') NOT NULL DEFAULT 'nova',
  `status` ENUM('rascunho','aguardando_contrato','aguardando_assinatura','confirmada','enturmada','abandonada','cancelada','lista_espera') NOT NULL DEFAULT 'rascunho',
  `aluno_id` INT(11) DEFAULT NULL,
  `ano_letivo_id` INT(11) DEFAULT NULL,
  `turma_id` INT(11) DEFAULT NULL,
  `serie_id` INT(11) DEFAULT NULL,
  `aluno_nome` VARCHAR(255) NOT NULL DEFAULT '',
  `aluno_cpf` VARCHAR(20) DEFAULT NULL,
  `aluno_rg` VARCHAR(40) DEFAULT NULL,
  `aluno_data_nasc` DATE DEFAULT NULL,
  `aluno_genero` VARCHAR(30) DEFAULT NULL,
  `aluno_email` VARCHAR(255) DEFAULT NULL,
  `aluno_telefone` VARCHAR(30) DEFAULT NULL,
  `aluno_endereco` TEXT DEFAULT NULL,
  `resp_nome` VARCHAR(255) NOT NULL DEFAULT '',
  `resp_cpf` VARCHAR(20) DEFAULT NULL,
  `resp_email` VARCHAR(255) DEFAULT NULL,
  `resp_telefone` VARCHAR(30) DEFAULT NULL,
  `resp_parentesco` VARCHAR(60) DEFAULT NULL,
  `resp_endereco` TEXT DEFAULT NULL,
  `finance_plan_id` INT(11) DEFAULT NULL,
  `finance_cobrancas` JSON DEFAULT NULL,
  `pagamento_status` ENUM('nao_solicitado','aguardando','pago','dispensado') NOT NULL DEFAULT 'nao_solicitado',
  `pagante_modo` VARCHAR(40) DEFAULT 'um',
  `documento_assinatura_codigo` VARCHAR(80) DEFAULT NULL,
  `dados_confirmados_em` DATETIME DEFAULT NULL,
  `contrato_pdf_path` VARCHAR(500) DEFAULT NULL,
  `contrato_token` VARCHAR(64) DEFAULT NULL,
  `contrato_hash` VARCHAR(64) DEFAULT NULL,
  `assinado_em` DATETIME DEFAULT NULL,
  `assinante_ip` VARCHAR(45) DEFAULT NULL,
  `assinante_nome` VARCHAR(255) DEFAULT NULL,
  `zapsign_doc_token` VARCHAR(120) DEFAULT NULL,
  `zapsign_signer_token` VARCHAR(120) DEFAULT NULL,
  `zapsign_sign_url` VARCHAR(500) DEFAULT NULL,
  `zapsign_status` VARCHAR(40) DEFAULT NULL,
  `zapsign_enviado_em` DATETIME DEFAULT NULL,
  `origem` ENUM('interno','site','whatsapp','indicacao','evento') NOT NULL DEFAULT 'interno',
  `observacoes` TEXT DEFAULT NULL,
  `expira_em` DATETIME DEFAULT NULL,
  `criado_por` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mp_contrato_token` (`contrato_token`),
  KEY `idx_mp_aluno` (`aluno_id`),
  KEY `idx_mp_turma` (`turma_id`),
  KEY `idx_mp_ano` (`ano_letivo_id`),
  KEY `idx_mp_status` (`status`),
  KEY `idx_mp_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 3) Colunas novas em matricula_processos (ALTER idempotente) ──────────────

SET @has_mp := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matricula_processos'
);

-- helper macro via blocos
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='aluno_rg');
SET @sql := IF(@has_mp>0 AND @col=0, "ALTER TABLE matricula_processos ADD COLUMN aluno_rg VARCHAR(40) DEFAULT NULL AFTER aluno_cpf", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='aluno_endereco');
SET @sql := IF(@has_mp>0 AND @col=0, "ALTER TABLE matricula_processos ADD COLUMN aluno_endereco TEXT DEFAULT NULL AFTER aluno_telefone", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='finance_plan_id');
SET @sql := IF(@has_mp>0 AND @col=0, "ALTER TABLE matricula_processos ADD COLUMN finance_plan_id INT(11) DEFAULT NULL AFTER resp_endereco", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='finance_cobrancas');
SET @sql := IF(@has_mp>0 AND @col=0, "ALTER TABLE matricula_processos ADD COLUMN finance_cobrancas JSON DEFAULT NULL AFTER finance_plan_id", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='pagamento_status');
SET @sql := IF(@has_mp>0 AND @col=0, "ALTER TABLE matricula_processos ADD COLUMN pagamento_status ENUM('nao_solicitado','aguardando','pago','dispensado') NOT NULL DEFAULT 'nao_solicitado' AFTER finance_cobrancas", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='pagante_modo');
SET @sql := IF(@has_mp>0 AND @col=0, "ALTER TABLE matricula_processos ADD COLUMN pagante_modo VARCHAR(40) DEFAULT 'um' AFTER pagamento_status", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='documento_assinatura_codigo');
SET @sql := IF(@has_mp>0 AND @col=0, "ALTER TABLE matricula_processos ADD COLUMN documento_assinatura_codigo VARCHAR(80) DEFAULT NULL AFTER pagante_modo", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='dados_confirmados_em');
SET @sql := IF(@has_mp>0 AND @col=0, "ALTER TABLE matricula_processos ADD COLUMN dados_confirmados_em DATETIME DEFAULT NULL AFTER documento_assinatura_codigo", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='zapsign_doc_token');
SET @sql := IF(@has_mp>0 AND @col=0, "ALTER TABLE matricula_processos ADD COLUMN zapsign_doc_token VARCHAR(120) DEFAULT NULL AFTER assinante_nome", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='zapsign_signer_token');
SET @sql := IF(@has_mp>0 AND @col=0, "ALTER TABLE matricula_processos ADD COLUMN zapsign_signer_token VARCHAR(120) DEFAULT NULL AFTER zapsign_doc_token", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='zapsign_sign_url');
SET @sql := IF(@has_mp>0 AND @col=0, "ALTER TABLE matricula_processos ADD COLUMN zapsign_sign_url VARCHAR(500) DEFAULT NULL AFTER zapsign_signer_token", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='zapsign_status');
SET @sql := IF(@has_mp>0 AND @col=0, "ALTER TABLE matricula_processos ADD COLUMN zapsign_status VARCHAR(40) DEFAULT NULL AFTER zapsign_sign_url", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='zapsign_enviado_em');
SET @sql := IF(@has_mp>0 AND @col=0, "ALTER TABLE matricula_processos ADD COLUMN zapsign_enviado_em DATETIME DEFAULT NULL AFTER zapsign_status", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 4) Auditoria / scores (criar se rename não rodou) ────────────────────────

CREATE TABLE IF NOT EXISTS `matricula_processos_auditorias` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `enrollment_id` INT(11) NOT NULL COMMENT 'FK lógica → matricula_processos.id',
  `status_de` VARCHAR(50) DEFAULT NULL,
  `status_para` VARCHAR(50) NOT NULL,
  `acao` VARCHAR(100) DEFAULT NULL,
  `usuario_id` INT(11) DEFAULT NULL,
  `usuario_nome` VARCHAR(255) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mpa_processo` (`enrollment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matricula_processos_scores` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `aluno_id` INT(11) NOT NULL,
  `ciclo` INT(4) NOT NULL,
  `score` TINYINT(3) NOT NULL DEFAULT 0,
  `faixa` ENUM('verde','amarelo','vermelho') NOT NULL DEFAULT 'verde',
  `freq_n` TINYINT(3) DEFAULT NULL,
  `desemp_n` TINYINT(3) DEFAULT NULL,
  `inad_n` TINYINT(3) DEFAULT NULL,
  `engaj_n` TINYINT(3) DEFAULT NULL,
  `tempo_n` TINYINT(3) DEFAULT NULL,
  `motivos` TEXT DEFAULT NULL,
  `calculado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mps_aluno_ciclo` (`aluno_id`, `ciclo`),
  KEY `idx_mps_faixa` (`faixa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5) Satélites ─────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `matricula_processos_responsaveis` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `enrollment_id` INT(11) NOT NULL COMMENT 'FK lógica → matricula_processos.id',
  `ordem` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `tipo_vinculo` VARCHAR(80) DEFAULT NULL,
  `is_pedagogico` TINYINT(1) NOT NULL DEFAULT 1,
  `is_financeiro` TINYINT(1) NOT NULL DEFAULT 0,
  `nome` VARCHAR(255) NOT NULL,
  `tipo_documento` ENUM('cpf','cnpj') NOT NULL DEFAULT 'cpf',
  `documento` VARCHAR(20) DEFAULT NULL,
  `rg` VARCHAR(40) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `telefone` VARCHAR(30) DEFAULT NULL,
  `endereco` TEXT DEFAULT NULL,
  `percentual` DECIMAL(5,2) DEFAULT NULL,
  `finance_contract_id` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mpr_processo` (`enrollment_id`),
  KEY `idx_mpr_ordem` (`enrollment_id`, `ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matricula_processos_produtos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `enrollment_id` INT(11) NOT NULL,
  `plan_item_id` INT(11) DEFAULT NULL,
  `tipo` ENUM('mensalidade','matricula','material_didatico','taxa','uniforme','outros') NOT NULL DEFAULT 'mensalidade',
  `descricao` VARCHAR(200) NOT NULL,
  `incluir` TINYINT(1) NOT NULL DEFAULT 1,
  `valor_base` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `num_parcelas` INT UNSIGNED NOT NULL DEFAULT 1,
  `mes_inicio` TINYINT UNSIGNED DEFAULT NULL,
  `fornecedor_externo` TINYINT(1) NOT NULL DEFAULT 0,
  `nome_instituicao` VARCHAR(200) DEFAULT NULL,
  `modelo_documento_codigo` VARCHAR(80) DEFAULT NULL,
  `finance_contract_id` INT UNSIGNED DEFAULT NULL,
  `status` ENUM('pendente','contratado','cancelado') NOT NULL DEFAULT 'pendente',
  `ordem` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mpp_processo` (`enrollment_id`),
  KEY `idx_mpp_tipo` (`enrollment_id`, `tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matricula_processos_documentos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `enrollment_id` INT(11) NOT NULL,
  `tipo` VARCHAR(80) NOT NULL DEFAULT 'outro',
  `nome_original` VARCHAR(255) NOT NULL DEFAULT '',
  `path` VARCHAR(500) NOT NULL,
  `mime` VARCHAR(120) DEFAULT NULL,
  `tamanho` INT UNSIGNED DEFAULT NULL,
  `criado_por` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mpd_processo` (`enrollment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
