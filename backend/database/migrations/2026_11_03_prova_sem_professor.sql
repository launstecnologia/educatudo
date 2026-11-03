-- Prova impressa elaborada pela escola: o professor deixa de ser obrigatório.
-- Só a matéria continua obrigatória. Tenant.
-- Rollback: 2026_11_03_prova_sem_professor_rollback.sql

SET @db := DATABASE();

SET @nullable := (
  SELECT IS_NULLABLE
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provas'
    AND COLUMN_NAME = 'professor_id'
);

SET @fk := (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provas'
    AND COLUMN_NAME = 'professor_id'
    AND REFERENCED_TABLE_NAME = 'professores'
  LIMIT 1
);

SET @sql := IF(
  @nullable = 'NO' AND @fk IS NOT NULL,
  CONCAT('ALTER TABLE `provas` DROP FOREIGN KEY `', @fk, '`'),
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  @nullable = 'NO',
  'ALTER TABLE `provas` MODIFY `professor_id` int DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk2 := (
  SELECT CONSTRAINT_NAME
  FROM information_schema.KEY_COLUMN_USAGE
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provas'
    AND COLUMN_NAME = 'professor_id'
    AND REFERENCED_TABLE_NAME = 'professores'
  LIMIT 1
);

SET @sql := IF(
  @fk2 IS NULL,
  'ALTER TABLE `provas` ADD CONSTRAINT `provas_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
