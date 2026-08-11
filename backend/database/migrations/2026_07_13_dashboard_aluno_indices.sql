-- Índices para otimizar dashboard do aluno (idempotente por escola).
-- Omitidos: provas_blocos_vinculo (UNIQUE bloco_prova) e alunos_acoes_diarias (idx_aluno_acao_data).

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provas_realizacoes'
      AND INDEX_NAME = 'idx_provas_realizacoes_aluno_status'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_provas_realizacoes_aluno_status ON provas_realizacoes (aluno_id, status, prova_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'provas_turmas'
      AND INDEX_NAME = 'idx_provas_turmas_turma_prova'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_provas_turmas_turma_prova ON provas_turmas (turma_id, prova_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'jornadas_progresso_alunos'
      AND INDEX_NAME = 'idx_jornadas_progresso_aluno_jornada'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_jornadas_progresso_aluno_jornada ON jornadas_progresso_alunos (aluno_id, jornada_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists := (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'mural_recados_vistos'
      AND INDEX_NAME = 'idx_mural_recados_vistos_aluno_recado'
);
SET @sql := IF(@idx_exists = 0,
    'CREATE INDEX idx_mural_recados_vistos_aluno_recado ON mural_recados_vistos (aluno_id, mural_recado_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
