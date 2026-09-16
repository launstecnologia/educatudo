-- Critério entre professores do mesmo componente: nenhum | media | soma
-- Tenant. Idempotente. Rollback: 2026_09_12_tipos_nota_criterio_professores_rollback.sql

SET @db := DATABASE();

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao'
);

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao'
    AND COLUMN_NAME = 'criterio_professores_mesmo_componente'
);
SET @sql := IF(
  @has > 0 AND @col = 0,
  "ALTER TABLE `provas_tipos_avaliacao`
     ADD COLUMN `criterio_professores_mesmo_componente` VARCHAR(20) NOT NULL DEFAULT 'nenhum'
       COMMENT 'nenhum|media|soma das notas dos professores do mesmo componente'",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_novo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao'
    AND COLUMN_NAME = 'criterio_professores_mesmo_componente'
);
SET @col_old := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao'
    AND COLUMN_NAME = 'media_professores_mesmo_componente'
);
SET @sql := IF(
  @col_novo > 0 AND @col_old > 0,
  "UPDATE provas_tipos_avaliacao
      SET criterio_professores_mesmo_componente = 'media'
    WHERE media_professores_mesmo_componente = 1
      AND criterio_professores_mesmo_componente = 'nenhum'",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
