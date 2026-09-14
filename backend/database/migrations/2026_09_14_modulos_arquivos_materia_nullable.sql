-- Permite criar/editar arquivos de admin sem informar matéria (campo já é opcional na tela)
SET @schema := DATABASE();

SET @col_nullable := (
  SELECT IS_NULLABLE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'modulos_arquivos' AND COLUMN_NAME = 'materia_id'
);
SET @sql_col := IF(@col_nullable = 'NO',
  'ALTER TABLE `modulos_arquivos` MODIFY `materia_id` int DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
