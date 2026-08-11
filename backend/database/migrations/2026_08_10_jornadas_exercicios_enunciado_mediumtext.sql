-- Amplia enunciado de exercícios da jornada para caber HTML com imagens do banco de questões.
-- TEXT (64KB) estourava em questões com figura embutida; MEDIUMTEXT cobre esse caso.

SET @db := DATABASE();

SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = @db
         AND TABLE_NAME = 'jornadas_modulos_exercicios'
         AND COLUMN_NAME = 'enunciado'
         AND DATA_TYPE = 'text'
    ),
    'ALTER TABLE jornadas_modulos_exercicios MODIFY enunciado MEDIUMTEXT NOT NULL',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
