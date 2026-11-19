-- MASTER apenas. Códigos de segundo fator do login do painel master.
-- O código em claro não é gravado: só o hash, com validade e limite de tentativas.

CREATE TABLE IF NOT EXISTS usuarios_master_codigos_acesso (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    codigo_hash VARCHAR(255) NOT NULL,
    expira_em DATETIME NOT NULL,
    tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
    usado_em DATETIME NULL DEFAULT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_codigos_usuario_aberto (usuario_id, usado_em, expira_em),
    CONSTRAINT fk_codigos_acesso_usuario_master
        FOREIGN KEY (usuario_id) REFERENCES usuarios_master(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Códigos de segundo fator do login do painel master';
