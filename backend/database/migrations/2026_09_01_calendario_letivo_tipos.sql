-- Tipos personalizados do Calendário Letivo (nome + cor).
-- Libera `calendario_letivo_eventos.tipo` de ENUM para VARCHAR e cria
-- `calendario_letivo_tipos` (sistema + cadastros da escola).
-- Tenant. Idempotente. Rollback: 2026_09_01_calendario_letivo_tipos_rollback.sql

SET @db := DATABASE();

SET @has_eventos := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo_eventos'
);

SET @tipo_data := (
  SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo_eventos' AND COLUMN_NAME = 'tipo'
);
SET @sql_tipo := IF(
  @has_eventos > 0 AND @tipo_data = 'enum',
  "ALTER TABLE `calendario_letivo_eventos` MODIFY COLUMN `tipo` VARCHAR(64) NOT NULL DEFAULT 'feriado'",
  'SELECT 1'
);
PREPARE stmt_tipo FROM @sql_tipo;
EXECUTE stmt_tipo;
DEALLOCATE PREPARE stmt_tipo;

CREATE TABLE IF NOT EXISTS `calendario_letivo_tipos` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `slug`       VARCHAR(64) NOT NULL,
  `nome`       VARCHAR(80) NOT NULL,
  `cor`        CHAR(7) NOT NULL DEFAULT '#374151',
  `cor_fundo`  CHAR(7) NOT NULL DEFAULT '#f3f4f6',
  `efeito`     ENUM('neutro','nao_letivo','reposicao') NOT NULL DEFAULT 'neutro',
  `sistema`    TINYINT(1) NOT NULL DEFAULT 0,
  `ordem`      INT(11) NOT NULL DEFAULT 100,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cal_tipos_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `calendario_letivo_tipos` (`slug`, `nome`, `cor`, `cor_fundo`, `efeito`, `sistema`, `ordem`)
SELECT 'feriado', 'Feriado', '#991b1b', '#fee2e2', 'nao_letivo', 1, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `calendario_letivo_tipos` WHERE `slug` = 'feriado');

INSERT INTO `calendario_letivo_tipos` (`slug`, `nome`, `cor`, `cor_fundo`, `efeito`, `sistema`, `ordem`)
SELECT 'recesso', 'Recesso', '#92400e', '#fef3c7', 'nao_letivo', 1, 2
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `calendario_letivo_tipos` WHERE `slug` = 'recesso');

INSERT INTO `calendario_letivo_tipos` (`slug`, `nome`, `cor`, `cor_fundo`, `efeito`, `sistema`, `ordem`)
SELECT 'reposicao', 'Reposição', '#166534', '#dcfce7', 'reposicao', 1, 3
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `calendario_letivo_tipos` WHERE `slug` = 'reposicao');

INSERT INTO `calendario_letivo_tipos` (`slug`, `nome`, `cor`, `cor_fundo`, `efeito`, `sistema`, `ordem`)
SELECT 'evento', 'Evento', '#1e40af', '#dbeafe', 'neutro', 1, 4
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `calendario_letivo_tipos` WHERE `slug` = 'evento');

INSERT INTO `calendario_letivo_tipos` (`slug`, `nome`, `cor`, `cor_fundo`, `efeito`, `sistema`, `ordem`)
SELECT 'suspensao', 'Suspensão', '#374151', '#f3f4f6', 'nao_letivo', 1, 5
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `calendario_letivo_tipos` WHERE `slug` = 'suspensao');

INSERT INTO `calendario_letivo_tipos` (`slug`, `nome`, `cor`, `cor_fundo`, `efeito`, `sistema`, `ordem`)
SELECT 'avaliacao', 'Avaliação', '#5b21b6', '#ede9fe', 'neutro', 1, 6
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `calendario_letivo_tipos` WHERE `slug` = 'avaliacao');
