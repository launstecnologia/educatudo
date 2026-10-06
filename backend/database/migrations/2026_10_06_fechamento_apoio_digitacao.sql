-- Apoio à digitação do fechamento (SED/SP e SERE/PR).
-- Arquivo interno para a secretaria copiar no sistema estadual. Não é layout de importação.
-- Cada geração é uma versão nova; a anterior permanece.
-- Tenant. Idempotente. Rollback: 2026_10_06_fechamento_apoio_digitacao_rollback.sql

SET @db := DATABASE();

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='fechamento_apoio_digitacao');
SET @sql := IF(@has=0,
  "CREATE TABLE `fechamento_apoio_digitacao` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `turma_id` INT NOT NULL,
    `ano_letivo` SMALLINT UNSIGNED NOT NULL,
    `periodo_tipo` VARCHAR(20) NOT NULL DEFAULT 'ano',
    `periodo_numero` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `uf_destino` CHAR(2) NOT NULL,
    `versao` INT NOT NULL,
    `status` ENUM('exportado','digitado','enviado','validado') NOT NULL DEFAULT 'exportado',
    `homologada` TINYINT(1) NOT NULL DEFAULT 0,
    `pendencias_json` MEDIUMTEXT NULL,
    `arquivo_planilha` VARCHAR(255) NULL,
    `arquivo_txt` VARCHAR(255) NULL,
    `protocolo` VARCHAR(80) NULL,
    `usuario_id` INT NULL,
    `usuario_nome` VARCHAR(180) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_apoio_digitacao_versao` (`turma_id`, `ano_letivo`, `periodo_tipo`, `periodo_numero`, `versao`),
    KEY `idx_apoio_digitacao_ano` (`ano_letivo`, `created_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='unidades' AND COLUMN_NAME='codigo_estadual');
SET @sql := IF(@col=0,
  "ALTER TABLE `unidades` ADD COLUMN `codigo_estadual` VARCHAR(30) NULL DEFAULT NULL COMMENT 'CIE (SP) ou código da escola no SERE (PR)' AFTER `inep`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND COLUMN_NAME='numero_classe_oficial');
SET @sql := IF(@col=0,
  "ALTER TABLE `turmas` ADD COLUMN `numero_classe_oficial` VARCHAR(40) NULL DEFAULT NULL COMMENT 'Número da classe na SED; distinto da turma interna'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND COLUMN_NAME='codigo_curso_oficial');
SET @sql := IF(@col=0,
  "ALTER TABLE `turmas` ADD COLUMN `codigo_curso_oficial` VARCHAR(40) NULL DEFAULT NULL COMMENT 'Código oficial do curso no sistema estadual'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos' AND COLUMN_NAME='ra_digito');
SET @sql := IF(@col=0,
  "ALTER TABLE `alunos` ADD COLUMN `ra_digito` VARCHAR(2) NULL DEFAULT NULL COMMENT 'Dígito do RA na SED' AFTER `ra`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos' AND COLUMN_NAME='ra_uf');
SET @sql := IF(@col=0,
  "ALTER TABLE `alunos` ADD COLUMN `ra_uf` CHAR(2) NULL DEFAULT NULL COMMENT 'UF do RA na SED' AFTER `ra_digito`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos' AND COLUMN_NAME='cgm');
SET @sql := IF(@col=0,
  "ALTER TABLE `alunos` ADD COLUMN `cgm` VARCHAR(30) NULL DEFAULT NULL COMMENT 'CGM do aluno no SERE/PR' AFTER `ra_uf`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
