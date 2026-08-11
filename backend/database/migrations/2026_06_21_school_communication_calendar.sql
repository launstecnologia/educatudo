-- Módulo independente de Comunicação Escolar e Calendário.
-- O mural_recados (professor -> aluno) permanece inalterado.

CREATE TABLE IF NOT EXISTS school_communications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(255) NOT NULL,
    conteudo TEXT NOT NULL,
    prioridade ENUM('normal','importante','urgente') NOT NULL DEFAULT 'normal',
    permite_resposta TINYINT(1) NOT NULL DEFAULT 1,
    publico ENUM('todos','turmas','alunos') NOT NULL,
    autor_tipo ENUM('admin','professor') NOT NULL,
    autor_id INT UNSIGNED NOT NULL,
    status ENUM('rascunho','publicado','arquivado') NOT NULL DEFAULT 'publicado',
    published_at DATETIME NULL,
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_school_communications_status (status, published_at),
    KEY idx_school_communications_priority (prioridade, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_communication_classes (
    communication_id BIGINT UNSIGNED NOT NULL,
    turma_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (communication_id, turma_id),
    KEY idx_school_communication_class (turma_id, communication_id),
    CONSTRAINT fk_school_communication_class FOREIGN KEY (communication_id) REFERENCES school_communications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_communication_students (
    communication_id BIGINT UNSIGNED NOT NULL,
    aluno_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (communication_id, aluno_id),
    KEY idx_school_communication_student (aluno_id, communication_id),
    CONSTRAINT fk_school_communication_student FOREIGN KEY (communication_id) REFERENCES school_communications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_communication_attachments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    communication_id BIGINT UNSIGNED NOT NULL,
    arquivo VARCHAR(500) NOT NULL,
    arquivo_nome VARCHAR(255) NOT NULL,
    tipo_arquivo VARCHAR(100) NULL,
    tamanho INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_school_communication_attachment (communication_id),
    CONSTRAINT fk_school_communication_attachment FOREIGN KEY (communication_id) REFERENCES school_communications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_communication_reads (
    communication_id BIGINT UNSIGNED NOT NULL,
    responsavel_id INT UNSIGNED NOT NULL,
    aluno_id INT UNSIGNED NOT NULL,
    lido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (communication_id, responsavel_id, aluno_id),
    KEY idx_school_communication_parent_read (responsavel_id, lido_em),
    CONSTRAINT fk_school_communication_read FOREIGN KEY (communication_id) REFERENCES school_communications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_communication_replies (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    communication_id BIGINT UNSIGNED NOT NULL,
    responsavel_id INT UNSIGNED NOT NULL,
    aluno_id INT UNSIGNED NOT NULL,
    sender_type ENUM('responsavel','admin','professor') NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    mensagem TEXT NOT NULL,
    lido_em DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_school_communication_reply (communication_id, created_at),
    CONSTRAINT fk_school_communication_reply FOREIGN KEY (communication_id) REFERENCES school_communications(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_calendar_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(255) NOT NULL,
    descricao TEXT NULL,
    categoria VARCHAR(50) NOT NULL DEFAULT 'evento',
    prioridade ENUM('normal','importante','urgente') NOT NULL DEFAULT 'normal',
    local VARCHAR(255) NULL,
    inicio_em DATETIME NOT NULL,
    fim_em DATETIME NULL,
    dia_inteiro TINYINT(1) NOT NULL DEFAULT 0,
    publico ENUM('todos','turmas','alunos') NOT NULL,
    status ENUM('rascunho','publicado','cancelado') NOT NULL DEFAULT 'publicado',
    criado_por INT UNSIGNED NOT NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_school_calendar_period (inicio_em, fim_em),
    KEY idx_school_calendar_status (status, inicio_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_calendar_event_classes (
    event_id BIGINT UNSIGNED NOT NULL,
    turma_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (event_id, turma_id),
    KEY idx_school_calendar_class (turma_id, event_id),
    CONSTRAINT fk_school_calendar_class FOREIGN KEY (event_id) REFERENCES school_calendar_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_calendar_event_students (
    event_id BIGINT UNSIGNED NOT NULL,
    aluno_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (event_id, aluno_id),
    KEY idx_school_calendar_student (aluno_id, event_id),
    CONSTRAINT fk_school_calendar_student FOREIGN KEY (event_id) REFERENCES school_calendar_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS school_calendar_event_reads (
    event_id BIGINT UNSIGNED NOT NULL,
    responsavel_id INT UNSIGNED NOT NULL,
    aluno_id INT UNSIGNED NOT NULL,
    lido_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id, responsavel_id, aluno_id),
    CONSTRAINT fk_school_calendar_read FOREIGN KEY (event_id) REFERENCES school_calendar_events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
