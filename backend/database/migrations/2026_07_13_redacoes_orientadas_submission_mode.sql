-- Coluna submission_mode (antes garantida em runtime pelo EssayProposal).
-- Idempotente: escolas que já receberam a coluna via runtime não quebram.
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'redacoes_orientadas_propostas'
      AND COLUMN_NAME = 'submission_mode'
);

SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE redacoes_orientadas_propostas ADD COLUMN submission_mode VARCHAR(20) NOT NULL DEFAULT ''texto''',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
