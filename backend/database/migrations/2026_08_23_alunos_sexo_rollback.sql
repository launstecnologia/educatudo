-- Rollback de 2026_08_23_alunos_sexo.sql
-- Remove a coluna só se existir. Tenants em que 059 já tinha criado sexo
-- também perdem a coluna ao reverter esta healing migration.

SET @db := DATABASE();

SET @col_sexo := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'alunos'
    AND COLUMN_NAME = 'sexo'
);
SET @sql_sexo := IF(
  @col_sexo > 0,
  'ALTER TABLE `alunos` DROP COLUMN `sexo`',
  'SELECT 1'
);
PREPARE stmt_sexo_rb FROM @sql_sexo;
EXECUTE stmt_sexo_rb;
DEALLOCATE PREPARE stmt_sexo_rb;
