-- Anexos (foto/documento) e testemunhas na ocorrência do aluno.
-- Rollback: 2026_08_31_ocorrencias_anexos_rollback.sql

SET @db := DATABASE();

SET @has_oc := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos_ocorrencias'
);

SET @col_testemunhas := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos_ocorrencias' AND COLUMN_NAME = 'testemunhas'
);
SET @sql_testemunhas := IF(
  @has_oc > 0 AND @col_testemunhas = 0,
  "ALTER TABLE `alunos_ocorrencias` ADD COLUMN `testemunhas` TEXT NULL AFTER `encaminhamento`",
  'SELECT 1'
);
PREPARE stmt_testemunhas FROM @sql_testemunhas;
EXECUTE stmt_testemunhas;
DEALLOCATE PREPARE stmt_testemunhas;

CREATE TABLE IF NOT EXISTS `ocorrencias_anexos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ocorrencia_id` INT NOT NULL,
  `nome` VARCHAR(255) NOT NULL,
  `caminho` VARCHAR(500) NOT NULL,
  `mime` VARCHAR(100) NULL DEFAULT NULL,
  `tamanho` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ocorrencias_anexos_ocorrencia` (`ocorrencia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_anexos := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'ocorrencias_anexos'
);
SET @fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'ocorrencias_anexos'
    AND CONSTRAINT_NAME = 'fk_ocorrencias_anexos_ocorrencia' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk := IF(
  @has_anexos > 0 AND @has_oc > 0 AND @fk = 0,
  "ALTER TABLE `ocorrencias_anexos` ADD CONSTRAINT `fk_ocorrencias_anexos_ocorrencia` FOREIGN KEY (`ocorrencia_id`) REFERENCES `alunos_ocorrencias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE",
  'SELECT 1'
);
PREPARE stmt_fk FROM @sql_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;
