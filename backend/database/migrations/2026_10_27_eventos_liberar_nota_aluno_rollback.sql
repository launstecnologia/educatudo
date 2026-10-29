-- Rollback de 2026_10_27_eventos_liberar_nota_aluno.sql

SET @db := DATABASE();
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'eventos_avaliativos'
    AND COLUMN_NAME = 'liberar_nota_aluno'
);
SET @sql := IF(
  @col > 0,
  'ALTER TABLE `eventos_avaliativos` DROP COLUMN `liberar_nota_aluno`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
