-- Regras Acadêmicas: critérios versionados de aprovação, recuperação e
-- frequência. O boletim (boletim_regras) continua compondo notas/layout;
-- a situação acadêmica passa a sair deste cadastro via ResultadoAcademicoService.
-- Tenant. Idempotente. Rollback: 2026_08_22_regras_academicas_rollback.sql

SET @db := DATABASE();

SET @has_regras := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='regras_academicas');
SET @sql := IF(@has_regras=0,
  "CREATE TABLE `regras_academicas` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nome` VARCHAR(150) NOT NULL,
    `codigo` VARCHAR(120) DEFAULT NULL,
    `ano_letivo` SMALLINT UNSIGNED DEFAULT NULL,
    `curso_id` INT DEFAULT NULL,
    `serie_id` INT DEFAULT NULL,
    `matriz_curricular_id` INT DEFAULT NULL,
    `materia_id` INT DEFAULT NULL COMMENT 'Exceção por componente; NULL = todos',
    `periodo_tipo` ENUM('bimestre','trimestre','semestre','etapa_unica') NOT NULL DEFAULT 'bimestre',
    `periodo_numero` TINYINT UNSIGNED DEFAULT NULL COMMENT '1-4; NULL = vale para o ano todo',
    `media_minima` DECIMAL(8,2) NOT NULL DEFAULT 6.00,
    `frequencia_minima` DECIMAL(5,2) NOT NULL DEFAULT 75.00,
    `usar_frequencia` TINYINT(1) NOT NULL DEFAULT 0,
    `round_mode` ENUM('none','half') NOT NULL DEFAULT 'none',
    `decimal_places` TINYINT(1) NOT NULL DEFAULT 2,
    `formula_media` TEXT DEFAULT NULL,
    `formula_final` TEXT DEFAULT NULL,
    `recuperacao_tipo` ENUM('nenhuma','continua','periodo','final') NOT NULL DEFAULT 'periodo',
    `recuperacao_composicao` ENUM('maior_nota','substitui','composicao','formula') NOT NULL DEFAULT 'maior_nota',
    `min_avaliacoes` SMALLINT UNSIGNED DEFAULT NULL,
    `max_avaliacoes` SMALLINT UNSIGNED DEFAULT NULL,
    `componentes_sem_nota` TINYINT(1) NOT NULL DEFAULT 0,
    `aprovacao_so_frequencia` TINYINT(1) NOT NULL DEFAULT 0,
    `situacoes_json` TEXT DEFAULT NULL,
    `observacoes` TEXT DEFAULT NULL,
    `versao` INT NOT NULL DEFAULT 1,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_regras_academicas_codigo` (`codigo`),
    KEY `idx_regras_academicas_ano` (`ano_letivo`, `ativo`),
    KEY `idx_regras_academicas_curso_serie` (`curso_id`, `serie_id`),
    KEY `idx_regras_academicas_materia` (`materia_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_hist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='regras_academicas_historico');
SET @sql := IF(@has_hist=0,
  "CREATE TABLE `regras_academicas_historico` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `regra_id` INT NOT NULL,
    `versao` INT NOT NULL,
    `parametros_json` TEXT NOT NULL,
    `usuario_id` INT DEFAULT NULL,
    `usuario_nome` VARCHAR(150) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_regras_academicas_historico_versao` (`regra_id`, `versao`),
    KEY `idx_regras_academicas_historico_regra` (`regra_id`, `created_at`),
    CONSTRAINT `fk_regras_acad_hist_regra` FOREIGN KEY (`regra_id`) REFERENCES `regras_academicas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FKs opcionais (tabelas alvo podem não existir em tenant antigo)
SET @has_regras_now := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='regras_academicas');

SET @has_curso := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='curso');
SET @fk_curso := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='regras_academicas' AND CONSTRAINT_NAME='fk_regras_acad_curso' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk_curso=0 AND @has_curso>0 AND @has_regras_now>0,
  "ALTER TABLE `regras_academicas` ADD CONSTRAINT `fk_regras_acad_curso` FOREIGN KEY (`curso_id`) REFERENCES `curso` (`id`) ON DELETE SET NULL ON UPDATE CASCADE",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_serie := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='serie');
SET @fk_serie := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='regras_academicas' AND CONSTRAINT_NAME='fk_regras_acad_serie' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk_serie=0 AND @has_serie>0 AND @has_regras_now>0,
  "ALTER TABLE `regras_academicas` ADD CONSTRAINT `fk_regras_acad_serie` FOREIGN KEY (`serie_id`) REFERENCES `serie` (`id`) ON DELETE SET NULL ON UPDATE CASCADE",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_materias := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias');
SET @fk_mat := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='regras_academicas' AND CONSTRAINT_NAME='fk_regras_acad_materia' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk_mat=0 AND @has_materias>0 AND @has_regras_now>0,
  "ALTER TABLE `regras_academicas` ADD CONSTRAINT `fk_regras_acad_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_matrizes := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matrizes_curriculares');
SET @fk_matriz := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='regras_academicas' AND CONSTRAINT_NAME='fk_regras_acad_matriz' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk_matriz=0 AND @has_matrizes>0 AND @has_regras_now>0,
  "ALTER TABLE `regras_academicas` ADD CONSTRAINT `fk_regras_acad_matriz` FOREIGN KEY (`matriz_curricular_id`) REFERENCES `matrizes_curriculares` (`id`) ON DELETE SET NULL ON UPDATE CASCADE",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
