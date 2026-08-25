-- Secretaria: vagas, fila, campanha de rematrícula, checklist, virada e Censo no funil.
-- Tenant. Idempotente. Rollback: 2026_08_15_matricula_secretaria_ciclo_rollback.sql

SET @db := DATABASE();

-- ── 1) Turma: capacidade e sucessão ───────────────────────────────────────────
SET @has_turmas := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND COLUMN_NAME='vagas');
SET @sql := IF(@has_turmas>0 AND @col=0,
  "ALTER TABLE turmas ADD COLUMN `vagas` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL ou 0 = sem limite'",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND COLUMN_NAME='turma_origem_id');
SET @sql := IF(@has_turmas>0 AND @col=0,
  "ALTER TABLE turmas ADD COLUMN `turma_origem_id` INT(11) NULL DEFAULT NULL COMMENT 'Turma do ano anterior (virada)'",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 2) Vínculo acadêmico: resultado do ano ────────────────────────────────────
SET @has_mat := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula');
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula' AND COLUMN_NAME='resultado_ano');
SET @sql := IF(@has_mat>0 AND @col=0,
  "ALTER TABLE matricula ADD COLUMN `resultado_ano` ENUM('nao_lancado','aprovado','reprovado','parcial') NOT NULL DEFAULT 'nao_lancado'",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 3) Processo: fila, campanha, reserva, Censo ───────────────────────────────
SET @has_mp := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='campanha_id');
SET @sql := IF(@has_mp>0 AND @col=0,
  "ALTER TABLE matricula_processos ADD COLUMN `campanha_id` INT UNSIGNED NULL DEFAULT NULL AFTER `serie_id`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='fila_posicao');
SET @sql := IF(@has_mp>0 AND @col=0,
  "ALTER TABLE matricula_processos ADD COLUMN `fila_posicao` INT UNSIGNED NULL DEFAULT NULL AFTER `campanha_id`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='entrou_fila_em');
SET @sql := IF(@has_mp>0 AND @col=0,
  "ALTER TABLE matricula_processos ADD COLUMN `entrou_fila_em` DATETIME NULL DEFAULT NULL AFTER `fila_posicao`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='reserva_ate');
SET @sql := IF(@has_mp>0 AND @col=0,
  "ALTER TABLE matricula_processos ADD COLUMN `reserva_ate` DATETIME NULL DEFAULT NULL AFTER `entrou_fila_em`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='aluno_nome_mae');
SET @sql := IF(@has_mp>0 AND @col=0,
  "ALTER TABLE matricula_processos
     ADD COLUMN `aluno_nome_mae` VARCHAR(255) NULL DEFAULT NULL AFTER `aluno_escola_anterior`,
     ADD COLUMN `aluno_nome_pai` VARCHAR(255) NULL DEFAULT NULL AFTER `aluno_nome_mae`,
     ADD COLUMN `aluno_codigo_inep` VARCHAR(20) NULL DEFAULT NULL AFTER `aluno_nome_pai`,
     ADD COLUMN `aluno_cor_raca` VARCHAR(20) NULL DEFAULT NULL AFTER `aluno_codigo_inep`,
     ADD COLUMN `aluno_nacionalidade` VARCHAR(60) NULL DEFAULT NULL AFTER `aluno_cor_raca`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 4) Planos: origem do clone (reajuste) ─────────────────────────────────────
SET @has_fp := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='finance_plans');
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='finance_plans' AND COLUMN_NAME='plano_origem_id');
SET @sql := IF(@has_fp>0 AND @col=0,
  "ALTER TABLE finance_plans ADD COLUMN `plano_origem_id` INT NULL DEFAULT NULL AFTER `serie_id`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 5) Campanhas de rematrícula ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `matricula_campanhas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(160) NOT NULL,
  `ano_origem_id` INT(11) NOT NULL,
  `ano_destino_id` INT(11) NOT NULL,
  `inicio` DATE NOT NULL,
  `fim` DATE NOT NULL,
  `status` ENUM('rascunho','aberta','encerrada') NOT NULL DEFAULT 'rascunho',
  `plano_padrao_id` INT NULL DEFAULT NULL,
  `reajuste_pct` DECIMAL(6,2) NULL DEFAULT NULL,
  `fila_auto_oferecer` TINYINT(1) NOT NULL DEFAULT 1,
  `exige_censo` TINYINT(1) NOT NULL DEFAULT 0,
  `criado_por` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mc_status` (`status`),
  KEY `idx_mc_anos` (`ano_origem_id`, `ano_destino_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matricula_campanha_planos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campanha_id` INT UNSIGNED NOT NULL,
  `plano_origem_id` INT NULL DEFAULT NULL,
  `serie_id` INT NULL DEFAULT NULL,
  `turma_origem_id` INT NULL DEFAULT NULL,
  `plano_destino_id` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mcp_campanha` (`campanha_id`),
  CONSTRAINT `fk_mcp_campanha` FOREIGN KEY (`campanha_id`) REFERENCES `matricula_campanhas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6) Checklist documental por tipo de processo ──────────────────────────────
CREATE TABLE IF NOT EXISTS `matricula_checklist_itens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo_processo` ENUM('nova','rematricula','transferencia') NOT NULL,
  `codigo` VARCHAR(80) NOT NULL,
  `rotulo` VARCHAR(160) NOT NULL,
  `obrigatorio` TINYINT(1) NOT NULL DEFAULT 1,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `ordem` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mci_tipo_codigo` (`tipo_processo`, `codigo`),
  KEY `idx_mci_tipo` (`tipo_processo`, `ativo`, `ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO matricula_checklist_itens (tipo_processo, codigo, rotulo, obrigatorio, ativo, ordem)
SELECT 'nova', 'rg', 'RG', 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM matricula_checklist_itens WHERE tipo_processo='nova' AND codigo='rg');
INSERT INTO matricula_checklist_itens (tipo_processo, codigo, rotulo, obrigatorio, ativo, ordem)
SELECT 'nova', 'cpf', 'CPF', 1, 1, 2 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM matricula_checklist_itens WHERE tipo_processo='nova' AND codigo='cpf');
INSERT INTO matricula_checklist_itens (tipo_processo, codigo, rotulo, obrigatorio, ativo, ordem)
SELECT 'nova', 'certidao', 'Certidão de nascimento', 1, 1, 3 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM matricula_checklist_itens WHERE tipo_processo='nova' AND codigo='certidao');
INSERT INTO matricula_checklist_itens (tipo_processo, codigo, rotulo, obrigatorio, ativo, ordem)
SELECT 'nova', 'comprovante_residencia', 'Comprovante de residência', 0, 1, 4 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM matricula_checklist_itens WHERE tipo_processo='nova' AND codigo='comprovante_residencia');

INSERT INTO matricula_checklist_itens (tipo_processo, codigo, rotulo, obrigatorio, ativo, ordem)
SELECT 'rematricula', 'comprovante_residencia', 'Comprovante de residência atualizado', 0, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM matricula_checklist_itens WHERE tipo_processo='rematricula' AND codigo='comprovante_residencia');

INSERT INTO matricula_checklist_itens (tipo_processo, codigo, rotulo, obrigatorio, ativo, ordem)
SELECT 'transferencia', 'historico', 'Histórico escolar da escola anterior', 1, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM matricula_checklist_itens WHERE tipo_processo='transferencia' AND codigo='historico');
INSERT INTO matricula_checklist_itens (tipo_processo, codigo, rotulo, obrigatorio, ativo, ordem)
SELECT 'transferencia', 'declaracao_transferencia', 'Declaração de transferência', 1, 1, 2 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM matricula_checklist_itens WHERE tipo_processo='transferencia' AND codigo='declaracao_transferencia');
INSERT INTO matricula_checklist_itens (tipo_processo, codigo, rotulo, obrigatorio, ativo, ordem)
SELECT 'transferencia', 'rg', 'RG', 1, 1, 3 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM matricula_checklist_itens WHERE tipo_processo='transferencia' AND codigo='rg');
INSERT INTO matricula_checklist_itens (tipo_processo, codigo, rotulo, obrigatorio, ativo, ordem)
SELECT 'transferencia', 'cpf', 'CPF', 1, 1, 4 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM matricula_checklist_itens WHERE tipo_processo='transferencia' AND codigo='cpf');

-- ── 7) Transferências (entrada/saída) com protocolo ───────────────────────────
CREATE TABLE IF NOT EXISTS `matricula_transferencias` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `protocolo` VARCHAR(40) NOT NULL,
  `direcao` ENUM('entrada','saida') NOT NULL,
  `aluno_id` INT(11) NULL DEFAULT NULL,
  `enrollment_id` INT(11) NULL DEFAULT NULL,
  `turma_origem_id` INT(11) NULL DEFAULT NULL,
  `escola_nome` VARCHAR(255) NULL DEFAULT NULL,
  `escola_cidade` VARCHAR(120) NULL DEFAULT NULL,
  `escola_uf` CHAR(2) NULL DEFAULT NULL,
  `escola_inep` VARCHAR(20) NULL DEFAULT NULL,
  `motivo` VARCHAR(255) NULL DEFAULT NULL,
  `observacao` TEXT NULL,
  `data_transferencia` DATE NOT NULL,
  `docs_entregues_em` DATETIME NULL DEFAULT NULL,
  `criado_por` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mt_protocolo` (`protocolo`),
  KEY `idx_mt_aluno` (`aluno_id`),
  KEY `idx_mt_direcao` (`direcao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
