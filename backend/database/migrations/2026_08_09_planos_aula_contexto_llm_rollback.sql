-- Remove contexto detalhado gerado por IA dos planos de aula.
-- Tenant. Idempotente.

SET @db := DATABASE();
SET @has_planos := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'planos_aula'
);
SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'planos_aula' AND COLUMN_NAME = 'contexto_llm'
);
SET @sql := IF(
    @has_planos > 0 AND @col > 0,
    "ALTER TABLE planos_aula DROP COLUMN `contexto_llm`",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
