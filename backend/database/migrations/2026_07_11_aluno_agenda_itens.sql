-- Agenda do aluno: itens pessoais que o próprio aluno adiciona, exibidos
-- junto com provas, jornadas, redação e calendário escolar na tela /agenda.
-- Executar em cada banco de tenant.

CREATE TABLE IF NOT EXISTS aluno_agenda_itens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    aluno_id    INT NOT NULL,
    titulo      VARCHAR(255) NOT NULL,
    descricao   TEXT NULL,
    data        DATE NOT NULL,
    created_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_aluno_data (aluno_id, data),
    CONSTRAINT aai_fk_aluno FOREIGN KEY (aluno_id) REFERENCES alunos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
