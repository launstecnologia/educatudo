-- Setup consolidado da Instituição (unidades) + documentos do aluno.
--
-- Reexecução segura da estrutura criada por 2026_06_25_unidades_escola.sql e
-- 2026_06_25_declaracoes_emitidas.sql. Útil para garantir a estrutura em tenants
-- onde a migration original foi marcada como executada mas não criou as tabelas.
--
-- Cria/garante:
--   - tabela `unidades` (matriz/filial + dados institucionais)
--   - coluna `alunos.unidade_id` + índice
--   - seed da unidade Matriz e backfill dos alunos sem unidade
--   - tabela `declaracoes_emitidas` (histórico/numeração das emissões)
--
-- Executar em cada banco de tenant. Idempotente.

CREATE TABLE IF NOT EXISTS `unidades` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `nome`            VARCHAR(255) NOT NULL,
  `tipo`            ENUM('matriz','filial') NOT NULL DEFAULT 'matriz',
  `razao_social`    VARCHAR(255) NULL,
  `cnpj`            VARCHAR(18)  NULL,
  `inep`            VARCHAR(20)  NULL,
  `dependencia_administrativa` VARCHAR(20) NULL,
  `endereco`        VARCHAR(255) NULL,
  `numero`          VARCHAR(20)  NULL,
  `complemento`     VARCHAR(120) NULL,
  `bairro`          VARCHAR(120) NULL,
  `cidade`          VARCHAR(120) NULL,
  `uf`              CHAR(2)      NULL,
  `cep`             VARCHAR(9)   NULL,
  `telefone`        VARCHAR(20)  NULL,
  `email`           VARCHAR(255) NULL,
  `diretor_nome`    VARCHAR(255) NULL,
  `secretario_nome` VARCHAR(255) NULL,
  `logo_url`        VARCHAR(500) NULL,
  `ativo`           TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`      DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_unidades_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db := DATABASE();

-- Coluna Censo: dependencia_administrativa (CREATE TABLE IF NOT EXISTS não altera tabela já existente)
SET @has_dep := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'unidades'
    AND COLUMN_NAME = 'dependencia_administrativa'
);
SET @sql := IF(
  @has_dep > 0,
  'SELECT 1',
  'ALTER TABLE unidades ADD COLUMN dependencia_administrativa VARCHAR(20) NULL AFTER inep'
);
PREPARE stmt_dep FROM @sql;
EXECUTE stmt_dep;
DEALLOCATE PREPARE stmt_dep;

-- Coluna alunos.unidade_id (só adiciona se não existir)
SET @has_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'alunos'
    AND COLUMN_NAME = 'unidade_id'
);
SET @sql := IF(
  @has_col > 0,
  'SELECT 1',
  'ALTER TABLE alunos ADD COLUMN unidade_id INT(11) NULL AFTER turma_id'
);
PREPARE stmt_unidade_col FROM @sql;
EXECUTE stmt_unidade_col;
DEALLOCATE PREPARE stmt_unidade_col;

-- Índice por unidade (só se não existir)
SET @has_idx := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'alunos'
    AND INDEX_NAME = 'idx_alunos_unidade'
);
SET @sql := IF(
  @has_idx > 0,
  'SELECT 1',
  'ALTER TABLE alunos ADD KEY idx_alunos_unidade (unidade_id)'
);
PREPARE stmt_unidade_idx FROM @sql;
EXECUTE stmt_unidade_idx;
DEALLOCATE PREPARE stmt_unidade_idx;

-- Seed: cria a unidade Matriz só se ainda não houver nenhuma unidade cadastrada
INSERT INTO `unidades` (`nome`, `tipo`)
SELECT 'Matriz', 'matriz'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `unidades`);

-- Backfill: vincula alunos sem unidade à unidade matriz (a mais antiga).
-- Só executa se a coluna existir — senão o UPDATE estoura 1054 na cláusula WHERE.
SET @has_col := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'alunos'
    AND COLUMN_NAME = 'unidade_id'
);
SET @sql := IF(
  @has_col > 0,
  'UPDATE alunos SET unidade_id = (SELECT MIN(id) FROM unidades) WHERE unidade_id IS NULL AND EXISTS (SELECT 1 FROM unidades)',
  'SELECT 1'
);
PREPARE stmt_unidade_backfill FROM @sql;
EXECUTE stmt_unidade_backfill;
DEALLOCATE PREPARE stmt_unidade_backfill;

-- Histórico/auditoria das declarações e documentos emitidos
CREATE TABLE IF NOT EXISTS `declaracoes_emitidas` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `aluno_id`      INT(11) NOT NULL,
  `unidade_id`    INT(11) NULL,
  `tipo`          VARCHAR(40) NOT NULL COMMENT 'matricula|frequencia|comparecimento|transferencia|historico|ficha_matricula|aut_*',
  `numero`        INT(11) NOT NULL DEFAULT 0 COMMENT 'Sequencial por ano',
  `ano`           SMALLINT(5) UNSIGNED NOT NULL,
  `emitido_por`   INT(11) NULL COMMENT 'usuarios.id do admin que emitiu',
  `emitido_nome`  VARCHAR(255) NULL,
  `meta_json`     TEXT NULL COMMENT 'Parâmetros usados (período, data, etc.)',
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_decl_aluno` (`aluno_id`),
  KEY `idx_decl_tipo` (`tipo`),
  KEY `idx_decl_ano_numero` (`ano`, `numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
