-- Garante unidades.dependencia_administrativa (Censo Escolar / Educacenso).
-- A migration 2026_06_25_censo_inep.sql pode ter rodado ANTES de unidades existir
-- (scandir: censo_inep < unidades_escola) e ter sido marcada como executada
-- sem criar a coluna. O dump educa_core.sql também criava a tabela sem este campo.
-- Idempotente. Tenant. Rollback: 2026_08_25_unidades_dependencia_administrativa_rollback.sql

SET @db := DATABASE();

SET @tem_unidades := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades'
);
SET @has_col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'dependencia_administrativa'
);
SET @sql := IF(
    @tem_unidades = 0 OR @has_col > 0,
    'SELECT 1',
    'ALTER TABLE unidades ADD COLUMN dependencia_administrativa VARCHAR(20) NULL AFTER inep'
);
PREPARE stmt_dep FROM @sql;
EXECUTE stmt_dep;
DEALLOCATE PREPARE stmt_dep;
