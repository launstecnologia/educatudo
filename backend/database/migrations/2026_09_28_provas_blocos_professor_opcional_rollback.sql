-- Volta a exigir professor no vínculo do bloco, se não houver linha sem professor.
SET @schema := DATABASE();

SET @null_count := (SELECT COUNT(*) FROM provas_blocos_professores WHERE professor_id IS NULL);
SET @col_nullable := (
  SELECT IS_NULLABLE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'provas_blocos_professores' AND COLUMN_NAME = 'professor_id'
);
SET @sql_col := IF(@col_nullable = 'YES' AND @null_count = 0,
  'ALTER TABLE `provas_blocos_professores` MODIFY `professor_id` int NOT NULL COMMENT ''ID do professor''',
  'SELECT 1'
);
PREPARE stmt FROM @sql_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
