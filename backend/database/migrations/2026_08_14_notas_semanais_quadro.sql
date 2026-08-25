-- Quadro de notas semanais (módulo notas-semanais).
-- Tenant. Idempotente.
-- Rollback: 2026_08_14_notas_semanais_quadro_rollback.sql

SET @db := DATABASE();

-- Semana S1–S8 no evento de prova
SET @has_pb := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos'
);
SET @col_semana := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'semana'
);
SET @col_bimestre := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'bimestre'
);
SET @sql := IF(
    @has_pb > 0 AND @col_semana = 0,
    IF(
        @col_bimestre > 0,
        "ALTER TABLE provas_blocos
           ADD COLUMN `semana` TINYINT UNSIGNED NULL DEFAULT NULL
             COMMENT 'Semana do bimestre no quadro (S1 a S8)'
             AFTER `bimestre`",
        "ALTER TABLE provas_blocos
           ADD COLUMN `semana` TINYINT UNSIGNED NULL DEFAULT NULL
             COMMENT 'Semana do bimestre no quadro (S1 a S8)'"
    ),
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_tipo := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'tipo_avaliacao_id'
);
SET @sql := IF(
    @has_pb > 0 AND @col_tipo = 0,
    "ALTER TABLE provas_blocos
       ADD COLUMN `tipo_avaliacao_id` INT UNSIGNED NULL DEFAULT NULL
         COMMENT 'Tipo de avaliação (semanal, bimestral, ENAC…)'
         AFTER `semana`",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @db
      AND CONSTRAINT_NAME = 'fk_provas_blocos_tipo_avaliacao'
      AND TABLE_NAME = 'provas_blocos'
);
SET @sql := IF(
    @has_pb > 0 AND @fk_exists = 0 AND (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao'
    ) > 0,
    "ALTER TABLE provas_blocos ADD CONSTRAINT fk_provas_blocos_tipo_avaliacao FOREIGN KEY (tipo_avaliacao_id) REFERENCES provas_tipos_avaliacao(id) ON UPDATE CASCADE ON DELETE SET NULL",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @has_tipos := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao'
);
SET @col_chave := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'chave_quadro'
);
SET @sql := IF(
    @has_tipos > 0 AND @col_chave = 0,
    "ALTER TABLE provas_tipos_avaliacao
       ADD COLUMN `chave_quadro` VARCHAR(32) NULL DEFAULT NULL
         COMMENT 'Papel no quadro de notas: semanal, prova_bim, enac, participacao, trabalho, recuperacao'
         AFTER `ordem`",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Só roda se provas_tipos_avaliacao existir (tenant sem 2026_05_12)
SET @sql := IF(
    @has_tipos > 0,
    "UPDATE provas_tipos_avaliacao SET chave_quadro = 'semanal' WHERE deleted_at IS NULL AND chave_quadro IS NULL AND LOWER(nome) LIKE '%semanal%'",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(
    @has_tipos > 0,
    "UPDATE provas_tipos_avaliacao SET chave_quadro = 'prova_bim' WHERE deleted_at IS NULL AND chave_quadro IS NULL AND LOWER(nome) LIKE '%bimestral%'",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(
    @has_tipos > 0,
    "UPDATE provas_tipos_avaliacao SET chave_quadro = 'enac' WHERE deleted_at IS NULL AND chave_quadro IS NULL AND LOWER(nome) = 'enac'",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(
    @has_tipos > 0,
    "INSERT IGNORE INTO provas_tipos_avaliacao (nome, descricao, ativo, ordem, chave_quadro) VALUES ('ENAC', 'Avaliação ENAC.', 1, 40, 'enac'), ('Participação', 'Nota de participação.', 1, 50, 'participacao'), ('Trabalho', 'Trabalho / atividade.', 1, 60, 'trabalho'), ('Recuperação', 'Recuperação bimestral.', 1, 70, 'recuperacao')",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(
    @has_tipos > 0,
    "UPDATE provas_tipos_avaliacao SET chave_quadro = 'enac' WHERE deleted_at IS NULL AND nome = 'ENAC' AND (chave_quadro IS NULL OR chave_quadro = '')",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(
    @has_tipos > 0,
    "UPDATE provas_tipos_avaliacao SET chave_quadro = 'participacao' WHERE deleted_at IS NULL AND nome = 'Participação' AND (chave_quadro IS NULL OR chave_quadro = '')",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(
    @has_tipos > 0,
    "UPDATE provas_tipos_avaliacao SET chave_quadro = 'trabalho' WHERE deleted_at IS NULL AND nome = 'Trabalho' AND (chave_quadro IS NULL OR chave_quadro = '')",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @sql := IF(
    @has_tipos > 0,
    "UPDATE provas_tipos_avaliacao SET chave_quadro = 'recuperacao' WHERE deleted_at IS NULL AND nome = 'Recuperação' AND (chave_quadro IS NULL OR chave_quadro = '')",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `notas_semanais_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `semanas_grupo_a` VARCHAR(32) NOT NULL DEFAULT '1,3,5,7',
  `semanas_grupo_b` VARCHAR(32) NOT NULL DEFAULT '2,4,6,8',
  `peso_media_sem` DECIMAL(5,2) NOT NULL DEFAULT 4.00,
  `peso_prova_bim` DECIMAL(5,2) NOT NULL DEFAULT 4.00,
  `peso_enac` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `peso_participacao` DECIMAL(5,2) NOT NULL DEFAULT 0.50,
  `peso_trabalho` DECIMAL(5,2) NOT NULL DEFAULT 0.50,
  `regra_recuperacao` VARCHAR(32) NOT NULL DEFAULT 'maior',
  `media_minima` DECIMAL(4,2) NOT NULL DEFAULT 6.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO notas_semanais_config (id) VALUES (1);

CREATE TABLE IF NOT EXISTS `notas_semanais_materias` (
  `materia_id` INT NOT NULL,
  `grupo` CHAR(1) NOT NULL COMMENT 'A ou B',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`materia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
