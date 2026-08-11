-- Coluna ativo: soft-delete de proposta de redação orientada (admin pode
-- "desativar" sem apagar histórico de envios/correções dos alunos).
-- Idempotente, mesmo padrão de 2026_07_13_redacoes_orientadas_submission_mode.sql.
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'redacoes_orientadas_propostas'
      AND COLUMN_NAME = 'ativo'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE redacoes_orientadas_propostas ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER status',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
