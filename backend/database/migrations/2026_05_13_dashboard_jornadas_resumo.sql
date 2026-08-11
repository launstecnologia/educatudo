CREATE TABLE IF NOT EXISTS dashboard_jornadas_resumo (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    segmento VARCHAR(32) NOT NULL,
    jornadas_escopo INT NOT NULL DEFAULT 0,
    pares_atribuidos INT NOT NULL DEFAULT 0,
    concluidos INT NOT NULL DEFAULT 0,
    pendentes INT NOT NULL DEFAULT 0,
    taxa_conclusao DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    atualizado_em DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dashboard_jornadas_resumo_segmento (segmento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
