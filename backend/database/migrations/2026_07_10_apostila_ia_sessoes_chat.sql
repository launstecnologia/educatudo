-- Fase B: sessões de chat, resumo rolling e sugestões dinâmicas por apostila.
-- Executar em cada banco de tenant.

CREATE TABLE IF NOT EXISTS apostila_ia_sessoes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    apostila_id     BIGINT UNSIGNED NOT NULL,
    usuario_id      BIGINT UNSIGNED NOT NULL,
    usuario_tipo    ENUM('professor', 'aluno') NOT NULL,
    titulo          VARCHAR(255) NOT NULL DEFAULT 'Nova conversa',
    resumo          TEXT NULL COMMENT 'Resumo rolling da sessão para contexto longo',
    created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_apostila_usuario (apostila_id, usuario_id, usuario_tipo),
    INDEX idx_apostila_updated (apostila_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_sessao := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'apostila_ia_conversas'
      AND COLUMN_NAME = 'sessao_id'
);
SET @sql_sessao := IF(
    @col_sessao = 0,
    'ALTER TABLE apostila_ia_conversas ADD COLUMN sessao_id BIGINT UNSIGNED NULL AFTER professor_id, ADD INDEX idx_sessao (sessao_id)',
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
    @col_sugestoes = 0,
    'ALTER TABLE apostilas_ia ADD COLUMN sugestoes_chat JSON NULL COMMENT ''Sugestões dinâmicas de perguntas para o chat'' AFTER vector_store_id',
    'SELECT 1'
);
PREPARE stmt_sugestoes FROM @sql_sugestoes;
EXECUTE stmt_sugestoes;
DEALLOCATE PREPARE stmt_sugestoes;
