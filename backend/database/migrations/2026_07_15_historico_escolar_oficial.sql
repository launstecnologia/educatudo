-- Histórico Escolar oficial (Educação Básica: Fundamental/Médio).
-- Atos legais na unidade + documento versionado com itens, resultados e assinaturas.
-- Tenant only. Sem escola_id (isolamento por conexão PDO).
-- Idempotente.
-- Pré-requisito recomendado: 2026_06_25_unidades_escola.sql (se unidades não existir,
-- os ALTERs abaixo são ignorados; as tabelas do histórico ainda são criadas).

-- ── 1. Atos legais / registro profissional na unidade ───────────────────────
SET @db := DATABASE();

SET @tem_unidades := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades'
);

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'ato_autorizacao'
);
SET @sql := IF(@tem_unidades = 0 OR @has > 0, 'SELECT 1',
  'ALTER TABLE unidades ADD COLUMN ato_autorizacao VARCHAR(255) NULL AFTER secretario_nome'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'ato_credenciamento'
);
SET @sql := IF(@tem_unidades = 0 OR @has > 0, 'SELECT 1',
  'ALTER TABLE unidades ADD COLUMN ato_credenciamento VARCHAR(255) NULL AFTER ato_autorizacao'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'ato_reconhecimento'
);
SET @sql := IF(@tem_unidades = 0 OR @has > 0, 'SELECT 1',
  'ALTER TABLE unidades ADD COLUMN ato_reconhecimento VARCHAR(255) NULL AFTER ato_credenciamento'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'diretor_registro'
);
SET @sql := IF(@tem_unidades = 0 OR @has > 0, 'SELECT 1',
  'ALTER TABLE unidades ADD COLUMN diretor_registro VARCHAR(80) NULL AFTER ato_reconhecimento'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'secretario_registro'
);
SET @sql := IF(@tem_unidades = 0 OR @has > 0, 'SELECT 1',
  'ALTER TABLE unidades ADD COLUMN secretario_registro VARCHAR(80) NULL AFTER diretor_registro'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ── 2. Documento (cabeçalho versionado) ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `historico_documentos` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `aluno_id`          INT(11) NOT NULL,
  `unidade_id`        INT(11) NULL,
  `versao`            INT(11) NOT NULL DEFAULT 1,
  `status`            ENUM('Rascunho','Conferido','Emitido','Assinado','Entregue','Cancelado')
                      NOT NULL DEFAULT 'Rascunho',
  `hash_validacao`    CHAR(64) NULL,
  `finalidade`        ENUM('Transferencia','Conclusao','Solicitacao') NOT NULL DEFAULT 'Solicitacao',
  `observacoes_gerais` TEXT NULL,
  `numero_registro_sed` VARCHAR(80) NULL COMMENT 'Nº SED/GDAE (Campo 6 modelo SP)',
  `snapshot_json`     LONGTEXT NULL COMMENT 'Foto imutável dos dados do aluno/unidade na emissão',
  `pdf_path`          VARCHAR(500) NULL,
  `emitido_em`        DATETIME NULL,
  `emitido_por`       INT(11) NULL,
  `conferido_em`      DATETIME NULL,
  `conferido_por`     INT(11) NULL,
  `substitui_id`      INT(11) NULL COMMENT 'Versão anterior cancelada',
  `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_historico_hash` (`hash_validacao`),
  KEY `idx_historico_aluno` (`aluno_id`),
  KEY `idx_historico_status` (`status`),
  KEY `idx_historico_aluno_versao` (`aluno_id`, `versao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Itens (ano letivo × componente) ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `historico_itens` (
  `id`                     INT(11) NOT NULL AUTO_INCREMENT,
  `historico_id`           INT(11) NOT NULL,
  `ano_letivo`             VARCHAR(9) NOT NULL,
  `serie_ano`              VARCHAR(80) NOT NULL,
  `componente`             VARCHAR(120) NOT NULL,
  `materia_id`             INT(11) NULL,
  `resultado_valor`        VARCHAR(40) NULL,
  `parecer_descritivo`     TEXT NULL,
  `carga_horaria`          INT(11) NULL,
  `frequencia_percentual`  DECIMAL(5,2) NULL,
  `origem`                 ENUM('Interno','Externo') NOT NULL DEFAULT 'Interno',
  `escola_origem`          VARCHAR(200) NULL,
  `ordem`                  INT(11) NOT NULL DEFAULT 0,
  `created_at`             DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_historico_itens_doc` (`historico_id`),
  KEY `idx_historico_itens_ano` (`historico_id`, `ano_letivo`),
  CONSTRAINT `fk_historico_itens_doc`
    FOREIGN KEY (`historico_id`) REFERENCES `historico_documentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Resultado final por ano letivo ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `historico_resultados_anuais` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `historico_id`  INT(11) NOT NULL,
  `ano_letivo`    VARCHAR(9) NOT NULL,
  `serie_ano`     VARCHAR(80) NOT NULL,
  `resultado`     ENUM('Aprovado','Aprovado_Conselho','Retido','Transferido','Evadido','Cursando')
                  NOT NULL DEFAULT 'Cursando',
  `observacao`    TEXT NULL,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_historico_resultado_ano` (`historico_id`, `ano_letivo`, `serie_ano`),
  CONSTRAINT `fk_historico_resultado_doc`
    FOREIGN KEY (`historico_id`) REFERENCES `historico_documentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Assinaturas eletrônicas simples ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `historico_assinaturas` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `historico_id`     INT(11) NOT NULL,
  `usuario_id`       INT(11) NOT NULL,
  `usuario_nome`     VARCHAR(255) NULL,
  `cargo`            ENUM('Diretor','Secretario_Escolar','Outro') NOT NULL,
  `numero_registro`  VARCHAR(80) NULL,
  `tipo`             ENUM('Eletronica_Simples','GovBr','ICP_Brasil') NOT NULL DEFAULT 'Eletronica_Simples',
  `assinado_em`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `ip_origem`        VARCHAR(45) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_historico_assinatura_cargo` (`historico_id`, `cargo`),
  KEY `idx_historico_assinaturas_doc` (`historico_id`),
  CONSTRAINT `fk_historico_assinatura_doc`
    FOREIGN KEY (`historico_id`) REFERENCES `historico_documentos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
