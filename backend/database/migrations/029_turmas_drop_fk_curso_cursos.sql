-- Ajuste: remove FK turmas.curso_id -> cursos (tabela cursos pode não existir ou ser legado).
-- Mantém a coluna curso_id; apenas remove a constraint para evitar erro ao importar dump
-- que tenha turmas com FK para cursos sem a tabela cursos no destino.
-- fk_turmas_serie (turmas.serie_id -> serie) permanece inalterada.

SET @fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'turmas'
    AND CONSTRAINT_NAME = 'fk_turmas_curso'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
  @fk_exists > 0,
  'ALTER TABLE `turmas` DROP FOREIGN KEY `fk_turmas_curso`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
