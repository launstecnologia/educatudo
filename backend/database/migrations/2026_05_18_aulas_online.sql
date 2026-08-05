CREATE TABLE IF NOT EXISTS aulas_online (
  id INT NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(255) NOT NULL,
  descricao TEXT NULL,
  plataforma VARCHAR(120) NULL,
  link_aula VARCHAR(700) NOT NULL,
  inicio_em DATETIME NOT NULL,
  fim_em DATETIME NULL,
  enviar_para_todos TINYINT(1) NOT NULL DEFAULT 0,
  publicado TINYINT(1) NOT NULL DEFAULT 1,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_por INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_aulas_online_inicio (inicio_em),
  KEY idx_aulas_online_publicado (publicado),
  KEY idx_aulas_online_ativo (ativo),
  KEY idx_aulas_online_criado_por (criado_por),
  CONSTRAINT fk_aulas_online_criado_por
    FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS aulas_online_turmas (
  aula_online_id INT NOT NULL,
  turma_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (aula_online_id, turma_id),
  KEY idx_aulas_online_turmas_turma (turma_id),
  CONSTRAINT fk_aulas_online_turmas_aula
    FOREIGN KEY (aula_online_id) REFERENCES aulas_online(id) ON DELETE CASCADE,
  CONSTRAINT fk_aulas_online_turmas_turma
    FOREIGN KEY (turma_id) REFERENCES turmas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
