-- 056_jornadas_listagem_indices_tenant.sql
-- Índices para acelerar a listagem de jornadas do aluno.

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'jornadas_aulas'
              AND INDEX_NAME = 'idx_ja_jornada_status'
        ),
        'SELECT 1',
        'ALTER TABLE jornadas_aulas ADD INDEX idx_ja_jornada_status (jornada_id, status)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'jornadas_exercicios'
              AND INDEX_NAME = 'idx_je_jornada_status'
        ),
        'SELECT 1',
        'ALTER TABLE jornadas_exercicios ADD INDEX idx_je_jornada_status (jornada_id, status)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'jornadas_modulos'
              AND INDEX_NAME = 'idx_jm_jornada'
        ),
        'SELECT 1',
        'ALTER TABLE jornadas_modulos ADD INDEX idx_jm_jornada (jornada_id)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'jornadas_progresso_alunos'
              AND INDEX_NAME = 'idx_jpa_jornada_aluno_status'
        ),
        'SELECT 1',
        'ALTER TABLE jornadas_progresso_alunos ADD INDEX idx_jpa_jornada_aluno_status (jornada_id, aluno_id, status)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'jornadas_progresso_alunos'
              AND INDEX_NAME = 'idx_jpa_jornada_aluno_tipo_status'
        ),
        'SELECT 1',
        'ALTER TABLE jornadas_progresso_alunos ADD INDEX idx_jpa_jornada_aluno_tipo_status (jornada_id, aluno_id, atividade_tipo, status)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
