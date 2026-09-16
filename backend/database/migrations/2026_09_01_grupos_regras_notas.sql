-- Grupo de Regras de Notas: tipos (A/B, Humanas…) e marcas (S1, S2…) por escola.
-- Tenant. Idempotente.
-- Rollback: 2026_09_01_grupos_regras_notas_rollback.sql

SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS `grupos_regras_notas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(150) NOT NULL,
  `descricao` VARCHAR(255) NULL DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_grupos_regras_notas_ativo` (`ativo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `grupos_regras_notas_tipos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grupo_id` INT UNSIGNED NOT NULL,
  `codigo` VARCHAR(60) NOT NULL,
  `nome` VARCHAR(150) NOT NULL,
  `ordem` INT NOT NULL DEFAULT 0,
  `tipo_avaliacao_id` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_grupos_regras_notas_tipos_codigo` (`grupo_id`, `codigo`),
  KEY `idx_grupos_regras_notas_tipos_grupo` (`grupo_id`),
  CONSTRAINT `fk_grn_tipos_grupo`
    FOREIGN KEY (`grupo_id`) REFERENCES `grupos_regras_notas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `grupos_regras_notas_marcas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grupo_id` INT UNSIGNED NOT NULL,
  `codigo` VARCHAR(60) NOT NULL,
  `nome` VARCHAR(150) NOT NULL,
  `numero` TINYINT UNSIGNED NOT NULL COMMENT 'Valor gravado em provas_blocos.semana (1-20)',
  `ordem` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_grupos_regras_notas_marcas_codigo` (`grupo_id`, `codigo`),
  UNIQUE KEY `uk_grupos_regras_notas_marcas_numero` (`grupo_id`, `numero`),
  KEY `idx_grupos_regras_notas_marcas_grupo` (`grupo_id`),
  CONSTRAINT `fk_grn_marcas_grupo`
    FOREIGN KEY (`grupo_id`) REFERENCES `grupos_regras_notas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `grupos_regras_notas_tipo_marcas` (
  `tipo_id` INT UNSIGNED NOT NULL,
  `marca_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`tipo_id`, `marca_id`),
  KEY `idx_grn_tipo_marcas_marca` (`marca_id`),
  CONSTRAINT `fk_grn_tipo_marcas_tipo`
    FOREIGN KEY (`tipo_id`) REFERENCES `grupos_regras_notas_tipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_grn_tipo_marcas_marca`
    FOREIGN KEY (`marca_id`) REFERENCES `grupos_regras_notas_marcas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `grupos_regras_notas_tipo_materias` (
  `tipo_id` INT UNSIGNED NOT NULL,
  `materia_id` INT NOT NULL,
  PRIMARY KEY (`tipo_id`, `materia_id`),
  KEY `idx_grn_tipo_materias_materia` (`materia_id`),
  CONSTRAINT `fk_grn_tipo_materias_tipo`
    FOREIGN KEY (`tipo_id`) REFERENCES `grupos_regras_notas_tipos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evento de prova: vínculo opcional com o grupo (semana continua sendo o número da marca)
SET @has_pb := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos'
);

SET @col_grupo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'grupo_regras_notas_id'
);
SET @sql := IF(
  @has_pb > 0 AND @col_grupo = 0,
  "ALTER TABLE `provas_blocos`
     ADD COLUMN `grupo_regras_notas_id` INT UNSIGNED NULL DEFAULT NULL
       COMMENT 'Grupo de regras de notas usado neste evento'
       AFTER `tipo_avaliacao_id`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_tipo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'grupo_regras_tipo_id'
);
SET @sql := IF(
  @has_pb > 0 AND @col_tipo = 0,
  "ALTER TABLE `provas_blocos`
     ADD COLUMN `grupo_regras_tipo_id` INT UNSIGNED NULL DEFAULT NULL
       COMMENT 'Tipo do grupo (A, B, Humanas…)'
       AFTER `grupo_regras_notas_id`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_marca := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'grupo_regras_marca_id'
);
SET @sql := IF(
  @has_pb > 0 AND @col_marca = 0,
  "ALTER TABLE `provas_blocos`
     ADD COLUMN `grupo_regras_marca_id` INT UNSIGNED NULL DEFAULT NULL
       COMMENT 'Marca do quadro (S1, S2…)'
       AFTER `grupo_regras_tipo_id`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_g := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_provas_blocos_grupo_regras'
);
SET @sql := IF(
  @has_pb > 0 AND @fk_g = 0 AND (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'grupo_regras_notas_id'
  ) > 0,
  "ALTER TABLE `provas_blocos`
     ADD CONSTRAINT `fk_provas_blocos_grupo_regras`
     FOREIGN KEY (`grupo_regras_notas_id`) REFERENCES `grupos_regras_notas` (`id`) ON DELETE SET NULL",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_t := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_provas_blocos_grupo_regras_tipo'
);
SET @sql := IF(
  @has_pb > 0 AND @fk_t = 0 AND (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'grupo_regras_tipo_id'
  ) > 0,
  "ALTER TABLE `provas_blocos`
     ADD CONSTRAINT `fk_provas_blocos_grupo_regras_tipo`
     FOREIGN KEY (`grupo_regras_tipo_id`) REFERENCES `grupos_regras_notas_tipos` (`id`) ON DELETE SET NULL",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_m := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_provas_blocos_grupo_regras_marca'
);
SET @sql := IF(
  @has_pb > 0 AND @fk_m = 0 AND (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'grupo_regras_marca_id'
  ) > 0,
  "ALTER TABLE `provas_blocos`
     ADD CONSTRAINT `fk_provas_blocos_grupo_regras_marca`
     FOREIGN KEY (`grupo_regras_marca_id`) REFERENCES `grupos_regras_notas_marcas` (`id`) ON DELETE SET NULL",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
