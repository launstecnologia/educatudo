-- Tabelas adicionais no banco master: limites por escola e vídeos tutoriais
-- Executar APENAS no banco master

CREATE TABLE IF NOT EXISTS limites_escolas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    escola_id INT UNSIGNED NOT NULL,
    max_alunos INT DEFAULT NULL,
    max_professores INT DEFAULT NULL,
    max_admins INT DEFAULT NULL,
    max_storage_mb INT DEFAULT NULL,
    max_tokens_ia_mes INT DEFAULT NULL,
    max_custo_ia_mes_usd DECIMAL(10,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_limites_escola (escola_id),
    CONSTRAINT fk_limites_escolas_escola
        FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Limites de uso por escola – banco master';

CREATE TABLE IF NOT EXISTS videos_tutoriais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT,
    url VARCHAR(500) NOT NULL,
    categoria VARCHAR(100) DEFAULT NULL,
    ordem INT DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Vídeos tutoriais globais – banco master';

CREATE TABLE IF NOT EXISTS videos_tutoriais_escolas (
    video_id INT NOT NULL,
    escola_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (video_id, escola_id),
    CONSTRAINT fk_vte_video FOREIGN KEY (video_id) REFERENCES videos_tutoriais(id) ON DELETE CASCADE,
    CONSTRAINT fk_vte_escola FOREIGN KEY (escola_id) REFERENCES escolas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT 'Visibilidade de vídeo tutorial por escola – banco master';
