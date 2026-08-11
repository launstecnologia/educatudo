-- Questões da API externa (professor)
CREATE TABLE IF NOT EXISTS professor_questoes_api (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    external_id VARCHAR(80) NOT NULL,
    materia VARCHAR(120) NULL,
    tipo VARCHAR(120) NULL,
    enunciado_html MEDIUMTEXT NULL,
    alternativas_json JSON NULL,
    gabarito VARCHAR(20) NULL,
    resolucao_html MEDIUMTEXT NULL,
    bncc TEXT NULL,
    tags TEXT NULL,
    topicos TEXT NULL,
    source_payload JSON NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prof_questoes_external (external_id),
    KEY idx_prof_questoes_materia (materia),
    KEY idx_prof_questoes_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Montagens de exercícios por professor
CREATE TABLE IF NOT EXISTS professor_questoes_montagens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    professor_id BIGINT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    observacoes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_prof_montagens_professor (professor_id),
    KEY idx_prof_montagens_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Itens de cada montagem
CREATE TABLE IF NOT EXISTS professor_questoes_montagem_itens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    montagem_id BIGINT UNSIGNED NOT NULL,
    questao_id BIGINT UNSIGNED NOT NULL,
    ordem INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prof_montagem_item (montagem_id, questao_id),
    KEY idx_prof_montagem_itens_montagem (montagem_id),
    KEY idx_prof_montagem_itens_questao (questao_id),
    CONSTRAINT fk_prof_montagem_itens_montagem
        FOREIGN KEY (montagem_id) REFERENCES professor_questoes_montagens(id) ON DELETE CASCADE,
    CONSTRAINT fk_prof_montagem_itens_questao
        FOREIGN KEY (questao_id) REFERENCES professor_questoes_api(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
