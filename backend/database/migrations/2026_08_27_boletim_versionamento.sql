-- Boletim: cada geração oficial vira uma nova versão vigente; as anteriores
-- ficam para auditoria (não se apagam). Aluno pode ser travado para não
-- entrar no recálculo em lote. Tenant. Idempotente.
-- Rollback: 2026_08_27_boletim_versionamento_rollback.sql

SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS `boletim_geracoes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `regra_id` INT NOT NULL,
  `periodo_ref` VARCHAR(60) NOT NULL,
  `versao` INT NOT NULL DEFAULT 1,
  `vigente` TINYINT(1) NOT NULL DEFAULT 1,
  `modo` VARCHAR(40) NOT NULL DEFAULT 'gerar',
  `usuario_id` INT NULL,
  `usuario_nome` VARCHAR(150) NULL,
  `alunos_processados` INT NOT NULL DEFAULT 0,
  `alunos_preservados` INT NOT NULL DEFAULT 0,
  `linhas_geradas` INT NOT NULL DEFAULT 0,
  `erros` INT NOT NULL DEFAULT 0,
  `alunos_mudanca_significativa` INT NOT NULL DEFAULT 0,
  `detalhes_json` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_boletim_geracoes_regra_periodo_versao` (`regra_id`, `periodo_ref`, `versao`),
  KEY `idx_boletim_geracoes_regra_vigente` (`regra_id`, `periodo_ref`, `vigente`),
  CONSTRAINT `fk_boletim_geracoes_regra` FOREIGN KEY (`regra_id`) REFERENCES `boletim_regras` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `boletim_alunos_travados` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `regra_id` INT NOT NULL,
  `aluno_id` INT NOT NULL,
  `periodo_ref` VARCHAR(60) NOT NULL,
  `motivo` VARCHAR(255) NULL,
  `usuario_id` INT NULL,
  `usuario_nome` VARCHAR(150) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_boletim_alunos_travados` (`regra_id`, `aluno_id`, `periodo_ref`),
  KEY `idx_boletim_alunos_travados_regra` (`regra_id`, `periodo_ref`),
  CONSTRAINT `fk_boletim_alunos_travados_regra` FOREIGN KEY (`regra_id`) REFERENCES `boletim_regras` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_boletim_alunos_travados_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_res := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados'
);

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados' AND COLUMN_NAME = 'geracao_id'
);
SET @sql := IF(@has_res > 0 AND @col = 0,
  "ALTER TABLE `boletim_resultados_gerados` ADD COLUMN `geracao_id` INT NULL DEFAULT NULL AFTER `preview`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados' AND COLUMN_NAME = 'versao'
);
SET @sql := IF(@has_res > 0 AND @col = 0,
  "ALTER TABLE `boletim_resultados_gerados` ADD COLUMN `versao` INT NOT NULL DEFAULT 1 AFTER `geracao_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados' AND COLUMN_NAME = 'vigente'
);
SET @sql := IF(@has_res > 0 AND @col = 0,
  "ALTER TABLE `boletim_resultados_gerados` ADD COLUMN `vigente` TINYINT(1) NOT NULL DEFAULT 1 AFTER `versao`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados' AND INDEX_NAME = 'idx_boletim_resultados_vigente'
);
SET @sql := IF(@has_res > 0 AND @idx = 0,
  "ALTER TABLE `boletim_resultados_gerados` ADD INDEX `idx_boletim_resultados_vigente` (`regra_id`, `aluno_id`, `periodo_ref`, `vigente`, `preview`)",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados' AND INDEX_NAME = 'idx_boletim_resultados_geracao'
);
SET @sql := IF(@has_res > 0 AND @idx = 0,
  "ALTER TABLE `boletim_resultados_gerados` ADD INDEX `idx_boletim_resultados_geracao` (`geracao_id`)",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_resultados_gerados'
    AND CONSTRAINT_NAME = 'fk_boletim_resultados_geracao' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(@has_res > 0 AND @fk = 0,
  "ALTER TABLE `boletim_resultados_gerados` ADD CONSTRAINT `fk_boletim_resultados_geracao` FOREIGN KEY (`geracao_id`) REFERENCES `boletim_geracoes` (`id`) ON DELETE SET NULL",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
