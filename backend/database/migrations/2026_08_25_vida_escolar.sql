-- Vida escolar: ficha oficial de boletim (ano corrente) + escolarização (histórico vivo)
-- + importação manual da trajetória de aluno transferido.
-- Tenant. Idempotente. Sem escola_id (isolamento por conexão PDO).
-- Rollback: 2026_08_25_vida_escolar_rollback.sql

-- ── 1. Ficha do boletim (1 por aluno × turma × ano) ──────────────────────────
CREATE TABLE IF NOT EXISTS `boletim_fichas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `aluno_id` INT NOT NULL,
  `turma_id` INT NULL,
  `matricula_id` INT NULL,
  `ano_letivo` SMALLINT UNSIGNED NOT NULL,
  `serie_nome` VARCHAR(80) NULL,
  `status` ENUM('em_curso','fechada','homologada') NOT NULL DEFAULT 'em_curso',
  `versao` INT NOT NULL DEFAULT 1,
  `homologada_em` DATETIME NULL,
  `homologada_por` INT NULL,
  `observacao` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_boletim_ficha_aluno_ano_turma` (`aluno_id`, `ano_letivo`, `turma_id`),
  KEY `idx_boletim_fichas_turma` (`turma_id`, `ano_letivo`, `status`),
  KEY `idx_boletim_fichas_aluno` (`aluno_id`, `ano_letivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. Linhas (componentes da matriz / grade) ────────────────────────────────
CREATE TABLE IF NOT EXISTS `boletim_ficha_linhas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ficha_id` INT NOT NULL,
  `materia_id` INT NULL,
  `componente_nome` VARCHAR(180) NOT NULL,
  `carga_horaria` INT NULL,
  `ordem` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_boletim_ficha_linhas_ficha` (`ficha_id`),
  KEY `idx_boletim_ficha_linhas_materia` (`materia_id`),
  CONSTRAINT `fk_boletim_ficha_linhas_ficha`
    FOREIGN KEY (`ficha_id`) REFERENCES `boletim_fichas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Células (bimestre 1-4 + FINAL=0) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `boletim_ficha_celulas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `linha_id` INT NOT NULL,
  `periodo_numero` TINYINT UNSIGNED NOT NULL COMMENT '1-4 bimestre; 0 = FINAL',
  `nota` DECIMAL(8,2) NULL,
  `conceito` VARCHAR(20) NULL,
  `faltas` INT NULL,
  `aulas_dadas` INT NULL,
  `origem` ENUM('vazia','calculada','externa','mista') NOT NULL DEFAULT 'vazia',
  `status` ENUM('aberta','fechada','homologada','reaberta') NOT NULL DEFAULT 'aberta',
  `escola_origem` VARCHAR(200) NULL,
  `documento_id` INT NULL,
  `nota_original` VARCHAR(40) NULL,
  `escala_original` VARCHAR(40) NULL,
  `observacao` VARCHAR(255) NULL,
  `versao` INT NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_boletim_ficha_celula` (`linha_id`, `periodo_numero`),
  KEY `idx_boletim_ficha_celulas_origem` (`origem`, `status`),
  CONSTRAINT `fk_boletim_ficha_celulas_linha`
    FOREIGN KEY (`linha_id`) REFERENCES `boletim_ficha_linhas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Auditoria append-only da ficha ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `boletim_ficha_auditoria` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ficha_id` INT NOT NULL,
  `celula_id` INT NULL,
  `acao` VARCHAR(40) NOT NULL,
  `campo` VARCHAR(40) NULL,
  `valor_anterior` TEXT NULL,
  `valor_novo` TEXT NULL,
  `motivo` TEXT NULL,
  `usuario_id` INT NULL,
  `usuario_nome` VARCHAR(150) NULL,
  `usuario_perfil` VARCHAR(40) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_boletim_ficha_auditoria_ficha` (`ficha_id`, `created_at`),
  KEY `idx_boletim_ficha_auditoria_celula` (`celula_id`),
  CONSTRAINT `fk_boletim_ficha_auditoria_ficha`
    FOREIGN KEY (`ficha_id`) REFERENCES `boletim_fichas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Escolarização (anos cursados — interno ou outra instituição) ──────────
CREATE TABLE IF NOT EXISTS `escolarizacao_anos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `aluno_id` INT NOT NULL,
  `ano_letivo` VARCHAR(9) NOT NULL,
  `serie_ano` VARCHAR(80) NOT NULL,
  `origem` ENUM('interno','externo') NOT NULL DEFAULT 'interno',
  `escola_nome` VARCHAR(200) NULL,
  `escola_inep` VARCHAR(20) NULL,
  `municipio` VARCHAR(120) NULL,
  `uf` CHAR(2) NULL,
  `resultado` VARCHAR(40) NULL,
  `carga_horaria_total` INT NULL,
  `faltas` INT NULL,
  `frequencia_percentual` DECIMAL(5,2) NULL,
  `observacao` TEXT NULL,
  `documento_id` INT NULL,
  `ficha_id` INT NULL COMMENT 'Quando originado do boletim desta escola',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_escolarizacao_aluno_ano_serie` (`aluno_id`, `ano_letivo`, `serie_ano`, `origem`),
  KEY `idx_escolarizacao_anos_aluno` (`aluno_id`, `ano_letivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `escolarizacao_componentes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ano_id` INT NOT NULL,
  `componente_original` VARCHAR(180) NOT NULL,
  `materia_id` INT NULL,
  `nota_original` VARCHAR(40) NULL,
  `escala_original` VARCHAR(40) NULL,
  `nota_convertida` DECIMAL(8,2) NULL,
  `carga_horaria` INT NULL,
  `frequencia_percentual` DECIMAL(5,2) NULL,
  `ordem` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_escolarizacao_comp_ano` (`ano_id`),
  CONSTRAINT `fk_escolarizacao_comp_ano`
    FOREIGN KEY (`ano_id`) REFERENCES `escolarizacao_anos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. Documentos recebidos (histórico, ficha, declaração) ───────────────────
CREATE TABLE IF NOT EXISTS `vida_escolar_documentos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `aluno_id` INT NOT NULL,
  `tipo` VARCHAR(40) NOT NULL DEFAULT 'historico',
  `escola_emissora` VARCHAR(200) NULL,
  `data_emissao` DATE NULL,
  `arquivo_key` VARCHAR(500) NULL,
  `arquivo_nome` VARCHAR(255) NULL,
  `arquivo_mime` VARCHAR(120) NULL,
  `arquivo_tamanho` INT UNSIGNED NULL,
  `status` ENUM('recebido','em_leitura','em_conferencia','validado','rejeitado','substituido') NOT NULL DEFAULT 'recebido',
  `substitui_id` INT NULL,
  `observacao` TEXT NULL,
  `enviado_por` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vida_escolar_docs_aluno` (`aluno_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. Importação (rascunho em JSON até validar) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `vida_escolar_importacoes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `aluno_id` INT NOT NULL,
  `documento_id` INT NULL,
  `escola_origem` VARCHAR(200) NULL,
  `escola_inep` VARCHAR(20) NULL,
  `municipio` VARCHAR(120) NULL,
  `uf` CHAR(2) NULL,
  `data_transferencia` DATE NULL,
  `data_entrada` DATE NULL,
  `status` ENUM('rascunho','em_conferencia','validada','cancelada') NOT NULL DEFAULT 'rascunho',
  `payload_json` LONGTEXT NULL,
  `resumo_json` TEXT NULL,
  `validada_por` INT NULL,
  `validada_em` DATETIME NULL,
  `cancelada_por` INT NULL,
  `cancelada_em` DATETIME NULL,
  `motivo_cancelamento` TEXT NULL,
  `criado_por` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vida_escolar_imp_aluno` (`aluno_id`, `status`),
  CONSTRAINT `fk_vida_escolar_imp_doc`
    FOREIGN KEY (`documento_id`) REFERENCES `vida_escolar_documentos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
