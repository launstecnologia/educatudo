-- Rollback: volta enunciado para TEXT (pode truncar conteúdos grandes já salvos).

SET @db := DATABASE();

SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = @db
         AND TABLE_NAME = 'jornadas_modulos_exercicios'
         AND COLUMN_NAME = 'enunciado'
         AND DATA_TYPE = 'mediumtext'
    ),
    'ALTER TABLE jornadas_modulos_exercicios MODIFY enunciado TEXT NOT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
