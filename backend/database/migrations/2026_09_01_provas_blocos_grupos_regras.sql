-- Vários destinos de grupo de regras no mesmo evento de prova.
-- Tenant. Idempotente.
-- Rollback: 2026_09_01_provas_blocos_grupos_regras_rollback.sql

SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS `provas_blocos_grupos_regras` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bloco_id` INT NOT NULL,
  `grupo_id` INT UNSIGNED NOT NULL,
  `tipo_id` INT UNSIGNED NULL DEFAULT NULL,
  `marca_id` INT UNSIGNED NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pbgr_bloco` (`bloco_id`),
  KEY `idx_pbgr_grupo` (`grupo_id`),
  KEY `idx_pbgr_tipo` (`tipo_id`),
  KEY `idx_pbgr_marca` (`marca_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fk_b := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_pbgr_bloco'
);
SET @sql := IF(
  @fk_b = 0 AND (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos'
  ) > 0,
  "ALTER TABLE `provas_blocos_grupos_regras`
     ADD CONSTRAINT `fk_pbgr_bloco`
     FOREIGN KEY (`bloco_id`) REFERENCES `provas_blocos` (`id`) ON DELETE CASCADE",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_g := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_pbgr_grupo'
);
SET @sql := IF(
  @fk_g = 0 AND (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'grupos_regras_notas'
  ) > 0,
  "ALTER TABLE `provas_blocos_grupos_regras`
     ADD CONSTRAINT `fk_pbgr_grupo`
     FOREIGN KEY (`grupo_id`) REFERENCES `grupos_regras_notas` (`id`) ON DELETE CASCADE",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_t := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_pbgr_tipo'
);
SET @sql := IF(
  @fk_t = 0 AND (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'grupos_regras_notas_tipos'
  ) > 0,
  "ALTER TABLE `provas_blocos_grupos_regras`
     ADD CONSTRAINT `fk_pbgr_tipo`
     FOREIGN KEY (`tipo_id`) REFERENCES `grupos_regras_notas_tipos` (`id`) ON DELETE SET NULL",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_m := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_pbgr_marca'
);
SET @sql := IF(
  @fk_m = 0 AND (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'grupos_regras_notas_marcas'
  ) > 0,
  "ALTER TABLE `provas_blocos_grupos_regras`
     ADD CONSTRAINT `fk_pbgr_marca`
     FOREIGN KEY (`marca_id`) REFERENCES `grupos_regras_notas_marcas` (`id`) ON DELETE SET NULL",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Copia o vínculo único já gravado em provas_blocos
SET @has_col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'grupo_regras_notas_id'
);
SET @sql := IF(
  @has_col > 0,
  "INSERT INTO `provas_blocos_grupos_regras` (`bloco_id`, `grupo_id`, `tipo_id`, `marca_id`)
   SELECT pb.id, pb.grupo_regras_notas_id, pb.grupo_regras_tipo_id, pb.grupo_regras_marca_id
     FROM provas_blocos pb
     LEFT JOIN provas_blocos_grupos_regras v ON v.bloco_id = pb.id
    WHERE pb.deleted_at IS NULL
      AND pb.grupo_regras_notas_id IS NOT NULL
      AND v.id IS NULL",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
