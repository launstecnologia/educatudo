-- Lançamento de notas pela coordenação pode vincular só a matéria, sem professor.
SET @schema := DATABASE();

SET @col_nullable := (
  SELECT IS_NULLABLE FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @schema AND TABLE_NAME = 'provas_blocos_professores' AND COLUMN_NAME = 'professor_id'
);
SET @sql_col := IF(@col_nullable = 'NO',
  'ALTER TABLE `provas_blocos_professores` MODIFY `professor_id` int DEFAULT NULL COMMENT ''ID do professor; vazio quando a coordenação lança a nota só pela matéria''',
  'SELECT 1'
);
PREPARE stmt FROM @sql_col;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
