-- Histórico de conversas do Assistente (admin/coordenação).
-- Isolamento por banco de tenant; conversas por usuario_id do admin.

CREATE TABLE IF NOT EXISTS assistente_conversas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT NOT NULL COMMENT 'usuarios.id do admin dono do chat',
    titulo VARCHAR(200) NOT NULL DEFAULT 'Nova conversa',
    aluno_id INT NULL,
    aluno_nome VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_assistente_conv_usuario (usuario_id, deleted_at, updated_at),
    KEY idx_assistente_conv_aluno (aluno_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS assistente_mensagens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversa_id INT UNSIGNED NOT NULL,
    role ENUM('user', 'assistant') NOT NULL,
    conteudo MEDIUMTEXT NOT NULL,
    painel_json JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_assistente_msg_conversa (conversa_id, id),
    CONSTRAINT fk_assistente_msg_conversa
        FOREIGN KEY (conversa_id) REFERENCES assistente_conversas (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
