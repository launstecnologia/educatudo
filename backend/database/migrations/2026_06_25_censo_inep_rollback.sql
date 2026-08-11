-- Rollback: Conformidade Censo/INEP (Fase 4)

SET @has_alunos_censo := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'nome_mae'
);
SET @sql_alunos_censo := IF(@has_alunos_censo = 0,
    'SELECT 1',
    'ALTER TABLE alunos
        DROP COLUMN nome_mae,
        DROP COLUMN nome_pai,
        DROP COLUMN codigo_inep'
);
PREPARE stmt_alunos_censo FROM @sql_alunos_censo;
EXECUTE stmt_alunos_censo;
DEALLOCATE PREPARE stmt_alunos_censo;

SET @has_unidade_dep := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'dependencia_administrativa'
);
SET @sql_unidade_dep := IF(@has_unidade_dep = 0,
    'SELECT 1',
    'ALTER TABLE unidades DROP COLUMN dependencia_administrativa'
);
PREPARE stmt_unidade_dep FROM @sql_unidade_dep;
EXECUTE stmt_unidade_dep;
DEALLOCATE PREPARE stmt_unidade_dep;
