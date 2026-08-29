-- Remove o índice de geração em massa da pauta do boletim.

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provas_blocos_notas_lancadas'
      AND INDEX_NAME = 'idx_notas_lancadas_aluno_bloco'
);
SET @sql := IF(@idx_exists > 0,
    'DROP INDEX idx_notas_lancadas_aluno_bloco ON provas_blocos_notas_lancadas',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
