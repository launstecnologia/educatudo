-- Quadro de Notas: rename físico grupos_regras_notas* → quadros_notas*
-- com VIEWS de transição (um release). Sem escola_id (isolamento por PDO).
-- Depende de 2026_09_01_grupos_regras_notas.sql (e vinculos de prova, se existirem).
-- Rollback: 2026_09_10_quadros_notas_rollback.sql
--
-- tipo_avaliacao_id em blocos do quadro permanece (não drop): o form já grava NULL;
-- chave_quadro em provas_tipos_avaliacao NÃO é morta — o assistente de boletim usa.

SET @db := DATABASE();

-- 1) Rename atômico das tabelas do quadro, se o nome novo ainda não existe.
SET @old := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas' AND TABLE_TYPE='BASE TABLE');
SET @new := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas' AND TABLE_TYPE='BASE TABLE');
SET @sql := IF(@old>0 AND @new=0,
  "RENAME TABLE
     `grupos_regras_notas_tipo_marcas` TO `quadros_notas_bloco_colunas`,
     `grupos_regras_notas_tipo_materias` TO `quadros_notas_bloco_materias`,
     `grupos_regras_notas_tipos` TO `quadros_notas_blocos`,
     `grupos_regras_notas_marcas` TO `quadros_notas_colunas`,
     `grupos_regras_notas` TO `quadros_notas`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2) Vínculos prova → quadro
SET @oldv := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='provas_blocos_grupos_regras' AND TABLE_TYPE='BASE TABLE');
SET @newv := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='provas_blocos_quadros_notas' AND TABLE_TYPE='BASE TABLE');
SET @sql := IF(@oldv>0 AND @newv=0,
  "RENAME TABLE `provas_blocos_grupos_regras` TO `provas_blocos_quadros_notas`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3) Views com os nomes antigos (SELECT * — colunas iguais).
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas');
SET @view := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas');
SET @sql := IF(@has>0 AND @view=0,
  "CREATE VIEW `grupos_regras_notas` AS SELECT * FROM `quadros_notas`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas_blocos');
SET @view := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_tipos');
SET @sql := IF(@has>0 AND @view=0,
  "CREATE VIEW `grupos_regras_notas_tipos` AS SELECT * FROM `quadros_notas_blocos`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas_colunas');
SET @view := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_marcas');
SET @sql := IF(@has>0 AND @view=0,
  "CREATE VIEW `grupos_regras_notas_marcas` AS SELECT * FROM `quadros_notas_colunas`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas_bloco_colunas');
SET @view := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_tipo_marcas');
SET @sql := IF(@has>0 AND @view=0,
  "CREATE VIEW `grupos_regras_notas_tipo_marcas` AS SELECT * FROM `quadros_notas_bloco_colunas`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas_bloco_materias');
SET @view := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas_tipo_materias');
SET @sql := IF(@has>0 AND @view=0,
  "CREATE VIEW `grupos_regras_notas_tipo_materias` AS SELECT * FROM `quadros_notas_bloco_materias`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='provas_blocos_quadros_notas');
SET @view := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='provas_blocos_grupos_regras');
SET @sql := IF(@has>0 AND @view=0,
  "CREATE VIEW `provas_blocos_grupos_regras` AS SELECT * FROM `provas_blocos_quadros_notas`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4) Campos explícitos do quadro (escala, critério, coluna consolidada, modo).
SET @tgt := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas' AND TABLE_TYPE='BASE TABLE')>0,
  'quadros_notas',
  IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas' AND TABLE_TYPE='BASE TABLE')>0,
     'grupos_regras_notas', NULL)
);

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='escala_max');
SET @sql := IF(@tgt IS NOT NULL AND @col=0,
  CONCAT('ALTER TABLE `', @tgt, '` ADD COLUMN `escala_max` DECIMAL(6,2) NOT NULL DEFAULT 10.00 COMMENT ''Escala máxima das colunas geradas'''),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='criterio_calculo');
SET @sql := IF(@tgt IS NOT NULL AND @col=0,
  CONCAT('ALTER TABLE `', @tgt, '` ADD COLUMN `criterio_calculo` VARCHAR(20) NOT NULL DEFAULT ''ultima'' COMMENT ''ultima|media|soma nas colunas do quadro'''),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='coluna_consolidada_codigo');
SET @sql := IF(@tgt IS NOT NULL AND @col=0,
  CONCAT('ALTER TABLE `', @tgt, '` ADD COLUMN `coluna_consolidada_codigo` VARCHAR(60) NULL DEFAULT ''media_sem'' COMMENT ''Código da coluna consolidada (média)'''),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='coluna_consolidada_nome');
SET @sql := IF(@tgt IS NOT NULL AND @col=0,
  CONCAT('ALTER TABLE `', @tgt, '` ADD COLUMN `coluna_consolidada_nome` VARCHAR(80) NULL DEFAULT ''Média'''),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tgt AND COLUMN_NAME='modo');
SET @sql := IF(@tgt IS NOT NULL AND @col=0,
  CONCAT('ALTER TABLE `', @tgt, '` ADD COLUMN `modo` VARCHAR(20) NOT NULL DEFAULT ''simples'' COMMENT ''simples (só colunas) | blocos (colunas + blocos de disciplinas)'''),
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5) Recria a view-mãe depois dos ADD COLUMN (SELECT * é expandido na CREATE VIEW).
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='quadros_notas');
SET @isview := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.VIEWS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas');
SET @sql := IF(@has>0 AND @isview>0, "DROP VIEW `grupos_regras_notas`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SET @existsOld := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='grupos_regras_notas');
SET @sql := IF(@has>0 AND @existsOld=0, "CREATE VIEW `grupos_regras_notas` AS SELECT * FROM `quadros_notas`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
