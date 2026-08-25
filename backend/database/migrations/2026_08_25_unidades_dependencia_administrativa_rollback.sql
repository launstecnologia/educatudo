-- Rollback: remove unidades.dependencia_administrativa se existir.
-- Justificativa: reverte 2026_08_25_unidades_dependencia_administrativa.sql.

SET @db := DATABASE();

SET @has_col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'dependencia_administrativa'
);
SET @sql := IF(
    @has_col = 0,
    'SELECT 1',
    'ALTER TABLE unidades DROP COLUMN dependencia_administrativa'
);
PREPARE stmt_dep FROM @sql;
EXECUTE stmt_dep;
DEALLOCATE PREPARE stmt_dep;
