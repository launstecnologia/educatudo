-- Fila de jobs assíncronos para chamadas de IA
-- Executar em cada banco de tenant

CREATE TABLE IF NOT EXISTS ai_jobs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_type      VARCHAR(64)  NOT NULL COMMENT 'gerar_exercicio | gerar_prova | corrigir_redacao | gerar_flashcards | gerar_slides',
    status        ENUM('pending','processing','done','failed') NOT NULL DEFAULT 'pending',
    payload       JSON         NOT NULL COMMENT 'Parâmetros de entrada serializados',
    result        JSON         NULL     COMMENT 'Resultado retornado pela IA',
    error_message TEXT         NULL,
    user_id       INT UNSIGNED NULL     COMMENT 'Usuário que disparou o job',
    user_role     VARCHAR(32)  NULL     COMMENT 'aluno | professor | admin',
    attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    started_at    DATETIME NULL,
    completed_at  DATETIME NULL,

    INDEX idx_status_created (status, created_at),
    INDEX idx_user (user_id),
    INDEX idx_cleanup (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
