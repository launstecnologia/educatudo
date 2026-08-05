-- Memória pessoal do aluno no chat da Tudinha: fatos que o aluno compartilha
-- (apelido preferido, objetivo de prova, área de interesse etc.) e que a IA
-- reusa espontaneamente em conversas futuras. Executar em cada banco de tenant.

CREATE TABLE IF NOT EXISTS tudinha_memorias_aluno (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aluno_id    INT NOT NULL,
    chave       VARCHAR(80) NOT NULL COMMENT 'slug curto definido pela IA, ex: apelido_preferido, prova_alvo',
    valor       VARCHAR(500) NOT NULL,
    created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_aluno_chave (aluno_id, chave),
    INDEX idx_aluno (aluno_id),
    CONSTRAINT tma_fk_aluno FOREIGN KEY (aluno_id) REFERENCES alunos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
