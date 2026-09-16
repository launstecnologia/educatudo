-- Rollback de 2026_09_12_tipos_nota_criterio_professores.sql

SET @db := DATABASE();

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_tipos_avaliacao'
    AND COLUMN_NAME = 'criterio_professores_mesmo_componente'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `provas_tipos_avaliacao` DROP COLUMN `criterio_professores_mesmo_componente`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
