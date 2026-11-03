-- Desfaz 2026_10_31_comunicacao_unificada.sql
-- Tenant. Recados com público série/curso voltam para "todos" antes de encolher o ENUM.

SET @db := DATABASE();

UPDATE `school_communications` SET `publico` = 'todos' WHERE `publico` IN ('series', 'cursos');

SET @tipo := (
  SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'school_communications' AND COLUMN_NAME = 'publico'
);
SET @sql := IF(@tipo IS NOT NULL AND @tipo LIKE '%series%',
  "ALTER TABLE `school_communications` MODIFY COLUMN `publico` ENUM('todos','turmas','alunos') NOT NULL",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'school_communications' AND COLUMN_NAME = 'enviar_push'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `school_communications` DROP COLUMN `enviar_push`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'school_communications' AND COLUMN_NAME = 'video_url'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `school_communications` DROP COLUMN `video_url`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `school_communication_cursos`;
DROP TABLE IF EXISTS `school_communication_series`;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'notificacoes' AND INDEX_NAME = 'idx_notificacoes_mural'
);
SET @sql := IF(@idx > 0, 'ALTER TABLE `notificacoes` DROP INDEX `idx_notificacoes_mural`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'notificacoes' AND COLUMN_NAME = 'mural_recado_id'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `notificacoes` DROP COLUMN `mural_recado_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mural_recados_vistos' AND INDEX_NAME = 'uniq_mural_professor'
);
SET @sql := IF(@idx > 0, 'ALTER TABLE `mural_recados_vistos` DROP INDEX `uniq_mural_professor`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Leituras de professor ficam com aluno_id nulo. Precisam sair antes de
-- voltar aluno_id para NOT NULL.
DELETE FROM `mural_recados_vistos` WHERE `aluno_id` IS NULL;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mural_recados_vistos' AND COLUMN_NAME = 'professor_id'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `mural_recados_vistos` DROP COLUMN `professor_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @nullable := (
  SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mural_recados_vistos' AND COLUMN_NAME = 'aluno_id'
);
SET @sql := IF(@nullable = 'YES',
  'ALTER TABLE `mural_recados_vistos` MODIFY COLUMN `aluno_id` INT UNSIGNED NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mural_recados' AND COLUMN_NAME = 'professor_id'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `mural_recados` DROP COLUMN `professor_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mural_recados' AND COLUMN_NAME = 'aluno_id'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `mural_recados` DROP COLUMN `aluno_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'mural_recados' AND COLUMN_NAME = 'publico'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `mural_recados` DROP COLUMN `publico`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
