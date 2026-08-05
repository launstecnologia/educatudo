-- Módulo "IA da Apostila": upload de PDFs processados por microserviço Python
-- (extração de texto por página, chunking, detecção de exercícios, vector store OpenAI)
-- e chat de professores com IA sobre o conteúdo da apostila.
-- Executar em cada banco de tenant.
-- Prefixo apostilas_ia / apostila_ia_* para não colidir com o módulo existente `modulos_apostilas`.

CREATE TABLE IF NOT EXISTS apostilas_ia (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    titulo          VARCHAR(255) NOT NULL,
    escola_id       BIGINT UNSIGNED NULL,
    serie_id        BIGINT UNSIGNED NULL,
    turma_id        BIGINT UNSIGNED NULL,
    disciplina_id   BIGINT UNSIGNED NULL,
    professor_id    BIGINT UNSIGNED NULL,
    arquivo_pdf     VARCHAR(500) NOT NULL,
    status          ENUM('pendente','processando','pronto','erro') NOT NULL DEFAULT 'pendente',
    total_paginas   INT NOT NULL DEFAULT 0,
    erro            TEXT NULL,
    vector_store_id VARCHAR(255) NULL COMMENT 'ID do vector store OpenAI gerado pelo microserviço Python',
    created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_professor (professor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS apostila_ia_paginas (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    apostila_id     BIGINT UNSIGNED NOT NULL,
    numero_pagina   INT NOT NULL,
    texto_extraido  LONGTEXT NULL,
    imagem_path     VARCHAR(500) NULL,
    created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_apostila_pagina (apostila_id, numero_pagina)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS apostila_ia_chunks (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    apostila_id         BIGINT UNSIGNED NOT NULL,
    pagina_inicio       INT NOT NULL,
    pagina_fim          INT NOT NULL,
    conteudo            LONGTEXT NOT NULL,
    metadata_json       JSON NULL,
    embedding_provider  VARCHAR(50) NOT NULL DEFAULT 'openai',
    created_at          TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_apostila (apostila_id),
    INDEX idx_paginas (pagina_inicio, pagina_fim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS apostila_ia_exercicios (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    apostila_id   BIGINT UNSIGNED NOT NULL,
    pagina        INT NOT NULL,
    capitulo      VARCHAR(255) NULL,
    tema          VARCHAR(255) NULL,
    tipo          ENUM('objetiva','discursiva','verdadeiro_falso','associacao','outro') NOT NULL DEFAULT 'outro',
    enunciado     LONGTEXT NOT NULL,
    alternativas  JSON NULL,
    gabarito      LONGTEXT NULL,
    dificuldade   ENUM('facil','media','dificil','nao_identificada') NOT NULL DEFAULT 'nao_identificada',
    created_at    TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_apostila_pagina (apostila_id, pagina),
    INDEX idx_tema (tema)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS apostila_ia_conversas (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    apostila_id     BIGINT UNSIGNED NOT NULL,
    professor_id    BIGINT UNSIGNED NULL,
    pergunta        LONGTEXT NOT NULL,
    resposta        LONGTEXT NOT NULL,
    paginas_usadas  JSON NULL,
    created_at      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_apostila_professor (apostila_id, professor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
