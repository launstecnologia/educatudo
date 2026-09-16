-- Finalidade do evento de boletim: oficial (matriz regular / histórico)
-- vs complementar (cursos extras, atividades paralelas).
-- Tenant. Idempotente. Rollback: 2026_09_02_boletim_finalidade_rollback.sql

SET @db := DATABASE();

SET @has_regras := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_regras'
);

SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'boletim_regras' AND COLUMN_NAME = 'finalidade'
);

SET @sql := IF(
  @has_regras > 0 AND @col = 0,
  "ALTER TABLE `boletim_regras` ADD COLUMN `finalidade` ENUM('oficial','complementar') NOT NULL DEFAULT 'oficial' COMMENT 'oficial=histórico; complementar=curso extra' AFTER `exibir_em`",
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
