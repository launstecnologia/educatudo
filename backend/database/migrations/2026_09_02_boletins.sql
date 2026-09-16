-- Cadastro de boletins (oficial / extra) separado dos eventos de notas.
-- Tenant. Idempotente. Rollback: 2026_09_02_boletins_rollback.sql

SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS `boletins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(180) NOT NULL,
  `finalidade` ENUM('oficial','complementar') NOT NULL DEFAULT 'oficial' COMMENT 'oficial=vida escolar; complementar=curso extra',
  `ano_letivo` SMALLINT UNSIGNED NULL DEFAULT NULL,
  `materias_ids` TEXT NULL,
  `series_ids` TEXT NULL,
  `turmas_ids` TEXT NULL,
  `nota_minima_aprovacao` DECIMAL(8,2) NULL DEFAULT NULL,
  `vis_aluno` TINYINT(1) NOT NULL DEFAULT 1,
  `vis_pais` TINYINT(1) NOT NULL DEFAULT 1,
  `vis_coordenacao` TINYINT(1) NOT NULL DEFAULT 1,
  `regra_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'boletim_regras do documento 1º–4º',
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_boletins_ativo` (`ativo`),
  KEY `idx_boletins_ano` (`ano_letivo`),
  KEY `idx_boletins_finalidade` (`finalidade`),
  KEY `idx_boletins_regra` (`regra_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_regras := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_regras'
);

SET @col_boletim := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_regras' AND COLUMN_NAME = 'boletim_id'
);

SET @sql := IF(
  @has_regras > 0 AND @col_boletim = 0,
  "ALTER TABLE `boletim_regras` ADD COLUMN `boletim_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Cadastro em boletins'",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_boletim := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_regras' AND INDEX_NAME = 'idx_boletim_regras_boletim'
);

SET @sql := IF(@has_regras > 0 AND @idx_boletim = 0, 'ALTER TABLE `boletim_regras` ADD KEY `idx_boletim_regras_boletim` (`boletim_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_boletim := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_regras' AND COLUMN_NAME = 'boletim_id'
);

-- Semeia cadastro a partir dos documentos já existentes (exibir_em=boletim).
SET @has_fin := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_regras' AND COLUMN_NAME = 'finalidade'
);

SET @sql := IF(
  @has_regras > 0 AND @has_fin > 0,
  "INSERT INTO `boletins` (nome, finalidade, ano_letivo, materias_ids, series_ids, turmas_ids, nota_minima_aprovacao, vis_aluno, vis_pais, vis_coordenacao, regra_id, ativo)
   SELECT r.nome,
          IF(r.finalidade = 'complementar', 'complementar', 'oficial'),
          r.ano_letivo, r.materias_ids, r.series_ids, r.turmas_ids, r.nota_minima_aprovacao,
          IFNULL(r.vis_aluno, 1), IFNULL(r.vis_pais, 1), IFNULL(r.vis_coordenacao, 1),
          r.id, 1
   FROM boletim_regras r
   WHERE r.exibir_em = 'boletim' AND r.ativo = 1
     AND NOT EXISTS (SELECT 1 FROM boletins b WHERE b.regra_id = r.id)",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Vincula eventos de notas ao cadastro único da mesma finalidade+ano.
SET @sql := IF(
  @has_regras > 0 AND @col_boletim > 0 AND @has_fin > 0,
  "UPDATE boletim_regras n
   INNER JOIN (
     SELECT finalidade, IFNULL(ano_letivo, 0) AS ano_key, MIN(id) AS boletim_id
     FROM boletins
     GROUP BY finalidade, IFNULL(ano_letivo, 0)
     HAVING COUNT(*) = 1
   ) u ON u.finalidade = IF(IFNULL(n.finalidade, 'oficial') = 'complementar', 'complementar', 'oficial')
      AND u.ano_key = IFNULL(n.ano_letivo, 0)
   SET n.boletim_id = u.boletim_id
   WHERE n.exibir_em = 'notas' AND n.boletim_id IS NULL",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
