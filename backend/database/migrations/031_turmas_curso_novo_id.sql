-- Turmas: vínculo com a tabela curso (nova estrutura), para incluir cursos extras no cadastro de turma.
-- curso_novo_id referencia curso(id); quando preenchido, a turma usa a estrutura nova (curso + opcional serie_id).

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'turmas'
    AND COLUMN_NAME = 'curso_novo_id'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `turmas` ADD COLUMN `curso_novo_id` int(11) DEFAULT NULL AFTER `curso_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'turmas'
    AND CONSTRAINT_NAME = 'fk_turmas_curso_novo'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
  @fk_exists = 0,
  'ALTER TABLE `turmas` ADD CONSTRAINT `fk_turmas_curso_novo` FOREIGN KEY (`curso_novo_id`) REFERENCES `curso` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
