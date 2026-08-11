-- 045_jornadas_exercicios_auditoria.sql
-- Auditoria completa de eventos de exercicios da jornada (aluno).

CREATE TABLE IF NOT EXISTS jornadas_exercicios_auditoria (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aluno_id INT NOT NULL,
    jornada_id INT NOT NULL,
    modulo_id INT NOT NULL,
    exercicio_id INT NOT NULL,
    tipo_acao VARCHAR(50) NOT NULL,
    de_valor TEXT NULL,
    para_valor TEXT NULL,
    resposta_final TEXT NULL,
    correto TINYINT(1) NULL,
    pontuacao DECIMAL(10,2) NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    detalhes_json TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_jornadas_auditoria_aluno (aluno_id),
    INDEX idx_jornadas_auditoria_jornada (jornada_id),
    INDEX idx_jornadas_auditoria_modulo (modulo_id),
    INDEX idx_jornadas_auditoria_exercicio (exercicio_id),
    INDEX idx_jornadas_auditoria_acao (tipo_acao),
    INDEX idx_jornadas_auditoria_data (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
