-- Rollback de 2026_10_28_regra_boletim.sql
-- Remove o vínculo de disciplinas e as colunas de modelo e público da regra.
-- Não apaga boletim já gravado.

SET @db := DATABASE();

SET @fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'regras_academicas_materias'
    AND CONSTRAINT_NAME = 'fk_regras_acad_materias_regra'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql := IF(
  @fk > 0,
  'ALTER TABLE `regras_academicas_materias` DROP FOREIGN KEY `fk_regras_acad_materias_regra`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `regras_academicas_materias`;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND COLUMN_NAME = 'vis_pais'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `regras_academicas` DROP COLUMN `vis_pais`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND COLUMN_NAME = 'vis_aluno'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `regras_academicas` DROP COLUMN `vis_aluno`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'regras_academicas' AND COLUMN_NAME = 'finalidade'
);
SET @sql := IF(@col > 0, 'ALTER TABLE `regras_academicas` DROP COLUMN `finalidade`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
