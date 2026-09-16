-- Regras de cálculo no Tipo de Nota + consolidado nota_final (boletim lê só essa nota).
-- Tenant. Idempotente. Rollback: 2026_09_12_tipos_nota_regras_rollback.sql

SET @db := DATABASE();

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao'
);

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'origem'
);
SET @sql := IF(
  @has > 0 AND @col = 0,
  "ALTER TABLE `provas_tipos_avaliacao`
     ADD COLUMN `origem` VARCHAR(32) NOT NULL DEFAULT 'lancamento_direto'
       COMMENT 'lancamento_direto|eventos|prova_online'",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'registro_evento'
);
SET @sql := IF(
  @has > 0 AND @col = 0,
  "ALTER TABLE `provas_tipos_avaliacao`
     ADD COLUMN `registro_evento` VARCHAR(32) NOT NULL DEFAULT 'nota'
       COMMENT 'nota|acertos_questoes' AFTER `origem`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'criterio_fechamento'
);
SET @sql := IF(
  @has > 0 AND @col = 0,
  "ALTER TABLE `provas_tipos_avaliacao`
     ADD COLUMN `criterio_fechamento` VARCHAR(32) NOT NULL DEFAULT 'ultima'
       COMMENT 'ultima|maior|media|soma|aproveitamento_nq' AFTER `registro_evento`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'escala_max'
);
SET @sql := IF(
  @has > 0 AND @col = 0,
  "ALTER TABLE `provas_tipos_avaliacao`
     ADD COLUMN `escala_max` DECIMAL(6,2) NOT NULL DEFAULT 10.00 AFTER `criterio_fechamento`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'quantidade_eventos_esperada'
);
SET @sql := IF(
  @has > 0 AND @col = 0,
  "ALTER TABLE `provas_tipos_avaliacao`
     ADD COLUMN `quantidade_eventos_esperada` INT UNSIGNED NULL
       COMMENT 'NULL = quantidade aberta' AFTER `escala_max`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'media_professores_mesmo_componente'
);
SET @sql := IF(
  @has > 0 AND @col = 0,
  "ALTER TABLE `provas_tipos_avaliacao`
     ADD COLUMN `media_professores_mesmo_componente` TINYINT(1) NOT NULL DEFAULT 0
       COMMENT '1 = média das notas dos professores do mesmo componente' AFTER `quantidade_eventos_esperada`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `notas_tipo_finais` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo_avaliacao_id` INT UNSIGNED NOT NULL,
  `aluno_id` INT NOT NULL,
  `materia_id` INT NOT NULL DEFAULT 0,
  `turma_id` INT NOT NULL DEFAULT 0,
  `ano_letivo` SMALLINT UNSIGNED NOT NULL,
  `periodo` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Número do período (bimestre 1–4)',
  `nota_final` DECIMAL(6,2) NOT NULL,
  `acertos_soma` INT UNSIGNED NOT NULL DEFAULT 0,
  `questoes_soma` INT UNSIGNED NOT NULL DEFAULT 0,
  `eventos_qtd` INT UNSIGNED NOT NULL DEFAULT 0,
  `calculado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nota_tipo_final` (`tipo_avaliacao_id`, `aluno_id`, `materia_id`, `turma_id`, `ano_letivo`, `periodo`),
  KEY `idx_nota_tipo_aluno_periodo` (`aluno_id`, `ano_letivo`, `periodo`),
  KEY `idx_nota_tipo_turma` (`turma_id`, `ano_letivo`, `periodo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = @db
    AND CONSTRAINT_NAME = 'fk_notas_tipo_finais_tipo'
    AND TABLE_NAME = 'notas_tipo_finais'
);
SET @sql := IF(
  @fk = 0 AND @has > 0,
  "ALTER TABLE `notas_tipo_finais`
     ADD CONSTRAINT `fk_notas_tipo_finais_tipo`
     FOREIGN KEY (`tipo_avaliacao_id`) REFERENCES `provas_tipos_avaliacao` (`id`)
     ON UPDATE CASCADE ON DELETE CASCADE",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_origem := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'origem'
);
SET @col_chave := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao' AND COLUMN_NAME = 'chave_quadro'
);

SET @sql := IF(
  @col_origem > 0 AND @col_chave > 0,
  "UPDATE provas_tipos_avaliacao
      SET origem = 'eventos',
          registro_evento = 'acertos_questoes',
          criterio_fechamento = 'aproveitamento_nq',
          media_professores_mesmo_componente = 1
    WHERE deleted_at IS NULL
      AND chave_quadro = 'semanal'
      AND origem = 'lancamento_direto'",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @col_origem > 0 AND @col_chave > 0,
  "UPDATE provas_tipos_avaliacao
      SET origem = 'eventos',
          registro_evento = 'acertos_questoes',
          criterio_fechamento = 'aproveitamento_nq'
    WHERE deleted_at IS NULL
      AND chave_quadro IS NULL
      AND LOWER(nome) LIKE '%semanal%'
      AND origem = 'lancamento_direto'",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
