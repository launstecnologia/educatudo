-- Liga o evento do calendário letivo ao aviso da família (school_calendar_events).
-- A edição e a exclusão no letivo passam a atualizar o mesmo aviso, sem criar cópia.
-- Inclui tipos neutros usados só para comunicar (reunião, passeio, apresentação).
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_31_calendario_aviso_familia_rollback.sql

SET @db := DATABASE();

SET @has_eventos := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo_eventos'
);

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'calendario_letivo_eventos'
    AND COLUMN_NAME = 'evento_escolar_id'
);
SET @sql := IF(
  @has_eventos > 0 AND @col = 0,
  'ALTER TABLE `calendario_letivo_eventos`
     ADD COLUMN `evento_escolar_id` BIGINT UNSIGNED NULL DEFAULT NULL
       COMMENT ''Aviso da família publicado a partir deste evento''
     AFTER `calendario_id`,
     ADD KEY `idx_cal_eventos_escolar` (`evento_escolar_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_tipos := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'calendario_letivo_tipos'
);
SET @sql_reuniao := IF(
  @has_tipos > 0,
  "INSERT INTO `calendario_letivo_tipos` (`slug`, `nome`, `cor`, `cor_fundo`, `efeito`, `sistema`, `ordem`)
   SELECT 'reuniao', 'Reunião', '#0f766e', '#ccfbf1', 'neutro', 1, 7
   FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `calendario_letivo_tipos` WHERE `slug` = 'reuniao')",
  'SELECT 1'
);
PREPARE stmt_reuniao FROM @sql_reuniao; EXECUTE stmt_reuniao; DEALLOCATE PREPARE stmt_reuniao;

SET @sql_passeio := IF(
  @has_tipos > 0,
  "INSERT INTO `calendario_letivo_tipos` (`slug`, `nome`, `cor`, `cor_fundo`, `efeito`, `sistema`, `ordem`)
   SELECT 'passeio', 'Passeio', '#c2410c', '#ffedd5', 'neutro', 1, 8
   FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `calendario_letivo_tipos` WHERE `slug` = 'passeio')",
  'SELECT 1'
);
PREPARE stmt_passeio FROM @sql_passeio; EXECUTE stmt_passeio; DEALLOCATE PREPARE stmt_passeio;

SET @sql_apresentacao := IF(
  @has_tipos > 0,
  "INSERT INTO `calendario_letivo_tipos` (`slug`, `nome`, `cor`, `cor_fundo`, `efeito`, `sistema`, `ordem`)
   SELECT 'apresentacao', 'Apresentação', '#9d174d', '#fce7f3', 'neutro', 1, 9
   FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `calendario_letivo_tipos` WHERE `slug` = 'apresentacao')",
  'SELECT 1'
);
PREPARE stmt_apresentacao FROM @sql_apresentacao; EXECUTE stmt_apresentacao; DEALLOCATE PREPARE stmt_apresentacao;
