-- Migration: Conformidade Censo/INEP (Fase 4)
-- Filiacao e codigo INEP do aluno; dependencia administrativa da unidade.
-- Idempotente via coluna-sentinela.

-- ============================================================
-- alunos: filiacao + codigo INEP
-- Sentinela: nome_mae
-- ============================================================
SET @has_alunos_censo := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'nome_mae'
);
SET @sql_alunos_censo := IF(@has_alunos_censo > 0,
    'SELECT 1',
    'ALTER TABLE alunos
        ADD COLUMN nome_mae VARCHAR(255) NULL,
        ADD COLUMN nome_pai VARCHAR(255) NULL,
        ADD COLUMN codigo_inep VARCHAR(20) NULL'
);
PREPARE stmt_alunos_censo FROM @sql_alunos_censo;
EXECUTE stmt_alunos_censo;
DEALLOCATE PREPARE stmt_alunos_censo;

-- ============================================================
-- unidades: dependencia administrativa (Censo)
-- Sentinela: dependencia_administrativa
-- Se a tabela unidades ainda não existir (migration de unidades pendente),
-- este bloco é ignorado com segurança.
-- ============================================================
SET @tem_unidades := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unidades'
);
SET @has_unidade_dep := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'unidades' AND COLUMN_NAME = 'dependencia_administrativa'
);
SET @sql_unidade_dep := IF(@tem_unidades = 0 OR @has_unidade_dep > 0,
    'SELECT 1',
    'ALTER TABLE unidades ADD COLUMN dependencia_administrativa VARCHAR(20) NULL AFTER inep'
);
PREPARE stmt_unidade_dep FROM @sql_unidade_dep;
EXECUTE stmt_unidade_dep;
DEALLOCATE PREPARE stmt_unidade_dep;
