-- Modo de juntar matérias iguais (vários professores): soma | media
-- Tenant. Idempotente. Rollback: 2026_09_30_boletim_componentes_materia_unica_modo_rollback.sql

SET @db := DATABASE();

SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_componentes'
);

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_componentes'
    AND COLUMN_NAME = 'materia_unica_modo'
);
SET @sql := IF(
  @has > 0 AND @col = 0,
  "ALTER TABLE `boletim_componentes`
     ADD COLUMN `materia_unica_modo` VARCHAR(10) NOT NULL DEFAULT 'soma'
       COMMENT 'soma|media ao juntar professores da mesma matéria'
       AFTER `materia_unica`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
