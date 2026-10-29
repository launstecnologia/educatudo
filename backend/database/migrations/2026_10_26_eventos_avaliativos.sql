-- Evento Avaliativo: pai que junta tipo de nota, quadro e sub-eventos (provas_blocos).
-- Tenant. Sem escola_id: o isolamento é o banco. Idempotente.
-- Rollback: 2026_10_26_eventos_avaliativos_rollback.sql

SET @db := DATABASE();

CREATE TABLE IF NOT EXISTS `eventos_avaliativos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(120) NOT NULL,
  `descricao` VARCHAR(255) NULL DEFAULT NULL,
  `ano_letivo` INT NULL DEFAULT NULL,
  `bimestre` TINYINT UNSIGNED NULL DEFAULT NULL,
  `tipo_avaliacao_id` INT UNSIGNED NULL DEFAULT NULL,
  `grupo_regras_notas_id` INT UNSIGNED NULL DEFAULT NULL,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `criado_por` INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_eventos_avaliativos_ano` (`ano_letivo`, `bimestre`),
  KEY `idx_eventos_avaliativos_tipo` (`tipo_avaliacao_id`),
  KEY `idx_eventos_avaliativos_grupo` (`grupo_regras_notas_id`),
  KEY `idx_eventos_avaliativos_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_pb := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos'
);
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'evento_avaliativo_id'
);
SET @sql := IF(
  @has_pb > 0 AND @col = 0,
  'ALTER TABLE `provas_blocos`
     ADD COLUMN `evento_avaliativo_id` INT UNSIGNED NULL DEFAULT NULL
       COMMENT ''eventos_avaliativos.id'',
     ADD KEY `idx_provas_blocos_evento_avaliativo` (`evento_avaliativo_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db AND CONSTRAINT_NAME = 'fk_provas_blocos_evento_avaliativo'
);
SET @col2 := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'evento_avaliativo_id'
);
SET @sql := IF(
  @has_pb > 0 AND @fk = 0 AND @col2 > 0,
  'ALTER TABLE `provas_blocos`
     ADD CONSTRAINT `fk_provas_blocos_evento_avaliativo`
     FOREIGN KEY (`evento_avaliativo_id`) REFERENCES `eventos_avaliativos` (`id`) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
