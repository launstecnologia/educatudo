-- Índice para a geração em massa do boletim (aluno_id IN (...) AND bloco_id IN (...)).
-- A unique key atual começa em bloco_id; com milhares de lançamentos do FII+EM
-- o filtro por aluno na pauta fica caro sem este índice.

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provas_blocos_notas_lancadas'
      AND INDEX_NAME = 'idx_notas_lancadas_aluno_bloco'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_notas_lancadas_aluno_bloco ON provas_blocos_notas_lancadas (aluno_id, bloco_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
