-- Censo Escolar / Educacenso: edição, complementos, validação, snapshot e exportação.
-- Reaproveita cadastros existentes (unidades, alunos, turmas, professores). Sem escola_id.
-- Tenant. Idempotente. Rollback: 2026_08_23_censo_escolar_rollback.sql

SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS `censo_layouts` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ano` SMALLINT UNSIGNED NOT NULL,
  `versao` VARCHAR(40) NOT NULL,
  `etapa_coleta` VARCHAR(40) NOT NULL DEFAULT 'matricula_inicial',
  `vigencia_inicio` DATE NULL DEFAULT NULL,
  `vigencia_fim` DATE NULL DEFAULT NULL,
  `configuracao_json` LONGTEXT NULL,
  `hash_configuracao` CHAR(64) NULL DEFAULT NULL,
  `fonte_oficial` VARCHAR(500) NULL DEFAULT NULL,
  `oficial` TINYINT(1) NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_layouts_ano_versao_etapa` (`ano`, `versao`, `etapa_coleta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_tabelas_auxiliares` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `layout_id` INT NOT NULL,
  `tabela` VARCHAR(80) NOT NULL,
  `codigo` VARCHAR(40) NOT NULL,
  `descricao` VARCHAR(255) NOT NULL,
  `metadados_json` JSON NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_aux_layout_tabela_codigo` (`layout_id`, `tabela`, `codigo`),
  KEY `idx_censo_aux_tabela` (`tabela`, `codigo`),
  CONSTRAINT `fk_censo_aux_layout` FOREIGN KEY (`layout_id`) REFERENCES `censo_layouts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_edicoes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `unidade_id` INT NOT NULL DEFAULT 0,
  `ano` SMALLINT UNSIGNED NOT NULL,
  `etapa_coleta` VARCHAR(40) NOT NULL DEFAULT 'matricula_inicial',
  `data_referencia` DATE NULL DEFAULT NULL,
  `versao_layout` VARCHAR(40) NULL DEFAULT NULL,
  `layout_id` INT NULL DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'rascunho',
  `responsavel_id` INT NULL DEFAULT NULL,
  `ultima_validacao_em` DATETIME NULL DEFAULT NULL,
  `ultima_validacao_por` INT NULL DEFAULT NULL,
  `fechado_em` DATETIME NULL DEFAULT NULL,
  `fechado_por` INT NULL DEFAULT NULL,
  `reaberto_em` DATETIME NULL DEFAULT NULL,
  `reaberto_por` INT NULL DEFAULT NULL,
  `motivo_reabertura` TEXT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_edicao_unidade_ano_etapa` (`unidade_id`, `ano`, `etapa_coleta`),
  KEY `idx_censo_edicoes_status` (`status`),
  KEY `idx_censo_edicoes_ano` (`ano`, `etapa_coleta`),
  KEY `idx_censo_edicoes_layout` (`layout_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_complementos_escola` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `edicao_id` INT NOT NULL,
  `unidade_id` INT NOT NULL DEFAULT 0,
  `status_validacao` VARCHAR(30) NOT NULL DEFAULT 'pendente',
  `conferido` TINYINT(1) NOT NULL DEFAULT 0,
  `incluir_exportacao` TINYINT(1) NOT NULL DEFAULT 1,
  `dados_json` JSON NULL,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_comp_escola_edicao` (`edicao_id`, `unidade_id`),
  CONSTRAINT `fk_censo_comp_escola_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_complementos_gestor` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `edicao_id` INT NOT NULL,
  `professor_id` INT NULL DEFAULT NULL,
  `usuario_id` INT NULL DEFAULT NULL,
  `cargo_codigo` VARCHAR(40) NULL DEFAULT NULL,
  `codigo_inep` VARCHAR(20) NULL DEFAULT NULL,
  `cpf` VARCHAR(14) NULL DEFAULT NULL,
  `nome` VARCHAR(255) NOT NULL,
  `status_validacao` VARCHAR(30) NOT NULL DEFAULT 'pendente',
  `conferido` TINYINT(1) NOT NULL DEFAULT 0,
  `incluir_exportacao` TINYINT(1) NOT NULL DEFAULT 1,
  `dados_json` JSON NULL,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_censo_gestor_edicao` (`edicao_id`),
  KEY `idx_censo_gestor_professor` (`professor_id`),
  CONSTRAINT `fk_censo_gestor_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_complementos_turma` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `edicao_id` INT NOT NULL,
  `turma_id` INT NOT NULL,
  `codigo_inep` VARCHAR(20) NULL DEFAULT NULL,
  `etapa_codigo` VARCHAR(20) NULL DEFAULT NULL,
  `modalidade_codigo` VARCHAR(20) NULL DEFAULT NULL,
  `status_validacao` VARCHAR(30) NOT NULL DEFAULT 'pendente',
  `conferido` TINYINT(1) NOT NULL DEFAULT 0,
  `incluir_exportacao` TINYINT(1) NOT NULL DEFAULT 1,
  `dados_json` JSON NULL,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_comp_turma_edicao` (`edicao_id`, `turma_id`),
  KEY `idx_censo_turma_etapa` (`etapa_codigo`),
  CONSTRAINT `fk_censo_comp_turma_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_complementos_aluno` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `edicao_id` INT NOT NULL,
  `aluno_id` INT NOT NULL,
  `status_validacao` VARCHAR(30) NOT NULL DEFAULT 'pendente',
  `conferido` TINYINT(1) NOT NULL DEFAULT 0,
  `incluir_exportacao` TINYINT(1) NOT NULL DEFAULT 1,
  `dados_json` JSON NULL,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_comp_aluno_edicao` (`edicao_id`, `aluno_id`),
  KEY `idx_censo_aluno_status` (`edicao_id`, `status_validacao`),
  CONSTRAINT `fk_censo_comp_aluno_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_complementos_profissional` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `edicao_id` INT NOT NULL,
  `professor_id` INT NOT NULL,
  `codigo_inep` VARCHAR(20) NULL DEFAULT NULL,
  `cpf` VARCHAR(14) NULL DEFAULT NULL,
  `status_validacao` VARCHAR(30) NOT NULL DEFAULT 'pendente',
  `conferido` TINYINT(1) NOT NULL DEFAULT 0,
  `incluir_exportacao` TINYINT(1) NOT NULL DEFAULT 1,
  `dados_json` JSON NULL,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_comp_prof_edicao` (`edicao_id`, `professor_id`),
  CONSTRAINT `fk_censo_comp_prof_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_matriculas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `edicao_id` INT NOT NULL,
  `aluno_id` INT NOT NULL,
  `turma_id` INT NOT NULL,
  `censo_turma_id` INT NULL DEFAULT NULL,
  `data_ingresso` DATE NULL DEFAULT NULL,
  `situacao_referencia` VARCHAR(40) NULL DEFAULT NULL,
  `status_validacao` VARCHAR(30) NOT NULL DEFAULT 'pendente',
  `incluir_exportacao` TINYINT(1) NOT NULL DEFAULT 1,
  `motivo_exclusao` VARCHAR(255) NULL DEFAULT NULL,
  `identificador_retorno` VARCHAR(40) NULL DEFAULT NULL,
  `dados_json` JSON NULL,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_matricula_edicao_aluno_turma` (`edicao_id`, `aluno_id`, `turma_id`),
  KEY `idx_censo_matricula_turma` (`turma_id`),
  CONSTRAINT `fk_censo_matricula_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_vinculos_profissionais` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `edicao_id` INT NOT NULL,
  `professor_id` INT NOT NULL,
  `turma_id` INT NOT NULL,
  `censo_turma_id` INT NULL DEFAULT NULL,
  `materia_id` INT NULL DEFAULT NULL,
  `componente_codigo` VARCHAR(20) NULL DEFAULT NULL,
  `funcao_codigo` VARCHAR(20) NULL DEFAULT NULL,
  `status_validacao` VARCHAR(30) NOT NULL DEFAULT 'pendente',
  `incluir_exportacao` TINYINT(1) NOT NULL DEFAULT 1,
  `dados_json` JSON NULL,
  `atualizado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_vinculo_edicao` (`edicao_id`, `professor_id`, `turma_id`, `materia_id`),
  CONSTRAINT `fk_censo_vinculo_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_situacoes_aluno` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `edicao_id` INT NOT NULL,
  `censo_matricula_id` INT NOT NULL,
  `aluno_id` INT NOT NULL,
  `situacao_codigo` VARCHAR(40) NULL DEFAULT NULL,
  `resultado_academico` VARCHAR(80) NULL DEFAULT NULL,
  `origem` VARCHAR(40) NOT NULL DEFAULT 'manual',
  `confirmado_por` INT NULL DEFAULT NULL,
  `confirmado_em` DATETIME NULL DEFAULT NULL,
  `justificativa` TEXT NULL,
  `status_validacao` VARCHAR(30) NOT NULL DEFAULT 'pendente',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_situacao_matricula` (`censo_matricula_id`),
  KEY `idx_censo_situacao_edicao` (`edicao_id`),
  CONSTRAINT `fk_censo_situacao_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_censo_situacao_matricula` FOREIGN KEY (`censo_matricula_id`) REFERENCES `censo_matriculas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_validacoes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `edicao_id` INT NOT NULL,
  `entidade_tipo` VARCHAR(40) NOT NULL,
  `entidade_id` INT NOT NULL DEFAULT 0,
  `regra_codigo` VARCHAR(80) NOT NULL,
  `severidade` VARCHAR(20) NOT NULL,
  `mensagem` VARCHAR(500) NOT NULL,
  `orientacao` VARCHAR(500) NULL DEFAULT NULL,
  `campo` VARCHAR(80) NULL DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'aberta',
  `justificativa` TEXT NULL,
  `resolvido_por` INT NULL DEFAULT NULL,
  `resolvido_em` DATETIME NULL DEFAULT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_censo_validacoes_edicao` (`edicao_id`, `severidade`, `status`),
  KEY `idx_censo_validacoes_entidade` (`entidade_tipo`, `entidade_id`),
  CONSTRAINT `fk_censo_validacoes_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_snapshots` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `edicao_id` INT NOT NULL,
  `versao` INT UNSIGNED NOT NULL,
  `dados_json` LONGTEXT NOT NULL,
  `hash` CHAR(64) NOT NULL,
  `criado_por` INT NULL DEFAULT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_censo_snapshot_edicao_versao` (`edicao_id`, `versao`),
  CONSTRAINT `fk_censo_snapshot_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_exportacoes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `edicao_id` INT NOT NULL,
  `snapshot_id` INT NULL DEFAULT NULL,
  `layout_id` INT NULL DEFAULT NULL,
  `versao` INT UNSIGNED NOT NULL,
  `tipo` VARCHAR(40) NOT NULL DEFAULT 'migracao',
  `arquivo` VARCHAR(500) NOT NULL,
  `nome_original` VARCHAR(255) NOT NULL,
  `hash_sha256` CHAR(64) NOT NULL,
  `tamanho_bytes` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_linhas` INT UNSIGNED NOT NULL DEFAULT 0,
  `resumo_json` JSON NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'gerado',
  `gerado_por` INT NULL DEFAULT NULL,
  `gerado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_censo_exportacoes_edicao` (`edicao_id`, `versao`),
  CONSTRAINT `fk_censo_exportacao_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_retornos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `edicao_id` INT NOT NULL,
  `exportacao_id` INT NULL DEFAULT NULL,
  `arquivo` VARCHAR(500) NOT NULL,
  `nome_original` VARCHAR(255) NOT NULL,
  `tipo` VARCHAR(40) NOT NULL DEFAULT 'inconsistencia',
  `hash_sha256` CHAR(64) NULL DEFAULT NULL,
  `resumo_json` JSON NULL,
  `aplicado` TINYINT(1) NOT NULL DEFAULT 0,
  `importado_por` INT NULL DEFAULT NULL,
  `importado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_censo_retornos_edicao` (`edicao_id`),
  KEY `idx_censo_retornos_exportacao` (`exportacao_id`),
  CONSTRAINT `fk_censo_retorno_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `censo_auditoria` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `edicao_id` INT NOT NULL,
  `usuario_id` INT NULL DEFAULT NULL,
  `acao` VARCHAR(60) NOT NULL,
  `entidade_tipo` VARCHAR(40) NULL DEFAULT NULL,
  `entidade_id` INT NULL DEFAULT NULL,
  `dados_anteriores_json` JSON NULL,
  `dados_novos_json` JSON NULL,
  `ip` VARCHAR(45) NULL DEFAULT NULL,
  `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_censo_auditoria_edicao` (`edicao_id`, `criado_em`),
  CONSTRAINT `fk_censo_auditoria_edicao` FOREIGN KEY (`edicao_id`) REFERENCES `censo_edicoes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
