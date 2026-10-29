-- Rollback de 2026_10_29_provas_blocos_agendas.sql
-- Apaga os horários por turma. A data do bloco permanece.

SET @db := DATABASE();

SET @fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provas_blocos_agendas'
    AND CONSTRAINT_NAME = 'fk_provas_blocos_agendas_turma'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
  @fk > 0,
  'ALTER TABLE `provas_blocos_agendas` DROP FOREIGN KEY `fk_provas_blocos_agendas_turma`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'provas_blocos_agendas'
    AND CONSTRAINT_NAME = 'fk_provas_blocos_agendas_bloco'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
  @fk > 0,
  'ALTER TABLE `provas_blocos_agendas` DROP FOREIGN KEY `fk_provas_blocos_agendas_bloco`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `provas_blocos_agendas`;
