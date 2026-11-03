-- Rollback de 2026_11_03_prova_sem_professor.sql
-- Só volta professor_id para NOT NULL se nenhuma prova estiver sem professor.

SET @db := DATABASE();

SET @nulls := (
  SELECT COUNT(*)
  FROM provas
  WHERE professor_id IS NULL
);

SET @nullable := (
  SELECT IS_NULLABLE
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provas'
    AND COLUMN_NAME = 'professor_id'
);

SET @sql := IF(
  @nullable = 'YES' AND @nulls = 0,
  'ALTER TABLE `provas` MODIFY `professor_id` int NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
