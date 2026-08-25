-- Unidades da escola (matriz/filial) + dados institucionais usados em documentos
-- oficiais (declarações). Vínculo N:1 do aluno com a unidade via alunos.unidade_id.
--
-- Cada escola continua sendo um tenant (um banco). Esta tabela permite que uma
-- mesma rede/mantenedora tenha mais de uma unidade dentro do mesmo tenant, cada
-- uma com CNPJ, endereço e responsáveis próprios para emissão de declarações.
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

-- Coluna alunos.unidade_id (só adiciona se não existir)
SET @db := DATABASE();
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
