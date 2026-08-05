-- Rollback Fase B: sessões de chat e sugestões dinâmicas.

SET @col_sessao := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'apostila_ia_conversas'
      AND COLUMN_NAME = 'sessao_id'
);
SET @sql_sessao := IF(
    @col_sessao > 0,
    'ALTER TABLE apostila_ia_conversas DROP COLUMN sessao_id',
    'SELECT 1'
);
PREPARE stmt_sessao FROM @sql_sessao;
EXECUTE stmt_sessao;
DEALLOCATE PREPARE stmt_sessao;

SET @col_sugestoes := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'apostilas_ia'
      AND COLUMN_NAME = 'sugestoes_chat'
);
SET @sql_sugestoes := IF(
    @col_sugestoes > 0,
    'ALTER TABLE apostilas_ia DROP COLUMN sugestoes_chat',
    'SELECT 1'
);
PREPARE stmt_sugestoes FROM @sql_sugestoes;
EXECUTE stmt_sugestoes;
DEALLOCATE PREPARE stmt_sugestoes;

DROP TABLE IF EXISTS apostila_ia_sessoes;
