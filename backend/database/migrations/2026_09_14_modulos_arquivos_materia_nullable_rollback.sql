-- Reverte materia_id para NOT NULL, somente se não houver registros com NULL
SET @schema := DATABASE();

SET @null_count := (SELECT COUNT(*) FROM modulos_arquivos WHERE materia_id IS NULL);
SET @col_nullable := (
  SELECT IS_NULLABLE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'modulos_arquivos' AND COLUMN_NAME = 'materia_id'
);
SET @sql_col := IF(@col_nullable = 'YES' AND @null_count = 0,
  'ALTER TABLE `modulos_arquivos` MODIFY `materia_id` int NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
