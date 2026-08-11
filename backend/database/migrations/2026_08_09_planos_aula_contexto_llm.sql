-- Adiciona contexto detalhado gerado por IA para reuso em jornadas/atividades.
-- Tenant. Idempotente. Rollback: 2026_08_09_planos_aula_contexto_llm_rollback.sql

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
    @has_planos > 0 AND @col = 0,
    "ALTER TABLE planos_aula
       ADD COLUMN `contexto_llm` LONGTEXT NULL
         COMMENT 'Texto detalhado gerado por IA para consumo por LLMs em jornadas e atividades'
         AFTER `observacoes`",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
