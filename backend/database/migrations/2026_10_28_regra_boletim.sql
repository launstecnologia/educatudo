-- Demonstrativo e boletim passam a herdar modelo, público e disciplinas da regra.
-- Tenant. Sem escola_id: o isolamento é o banco. Idempotente.
-- Rollback: 2026_10_28_regra_boletim_rollback.sql

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND COLUMN_NAME = 'finalidade'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `regras_academicas`
     ADD COLUMN `finalidade` ENUM(''oficial'',''complementar'') NOT NULL DEFAULT ''oficial''
       COMMENT ''oficial = vida escolar; complementar = modelo extra''
     AFTER `ativo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND COLUMN_NAME = 'vis_aluno'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `regras_academicas`
     ADD COLUMN `vis_aluno` TINYINT(1) NOT NULL DEFAULT 1 AFTER `finalidade`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND COLUMN_NAME = 'vis_pais'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `regras_academicas`
     ADD COLUMN `vis_pais` TINYINT(1) NOT NULL DEFAULT 1 AFTER `vis_aluno`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `regras_academicas_materias` (
  `regra_id` INT NOT NULL,
  `materia_id` INT NOT NULL,
  PRIMARY KEY (`regra_id`, `materia_id`),
  KEY `idx_regras_academicas_materias_materia` (`materia_id`),
  CONSTRAINT `fk_regras_acad_materias_regra`
    FOREIGN KEY (`regra_id`) REFERENCES `regras_academicas` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
