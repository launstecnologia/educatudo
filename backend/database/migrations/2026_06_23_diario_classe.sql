CREATE TABLE IF NOT EXISTS diario_aulas (
  id INT NOT NULL AUTO_INCREMENT,
  grade_horaria_id INT NOT NULL,
  professor_id INT NOT NULL,
  turma_id INT NOT NULL,
  materia_id INT NOT NULL,
  plano_aula_id INT NULL,
  data_aula DATE NOT NULL,
  horario_de TIME NOT NULL,
  horario_ate TIME NOT NULL,
  execucao ENUM('conforme_planejado','parcial','alterado','nao_realizada') NOT NULL DEFAULT 'conforme_planejado',
  conteudo_realizado TEXT NULL,
  observacoes TEXT NULL,
  status ENUM('rascunho','finalizada','cancelada') NOT NULL DEFAULT 'rascunho',
  finalizada_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_diario_grade_data (grade_horaria_id, data_aula),
  KEY idx_diario_prof_data (professor_id, data_aula),
  KEY idx_diario_turma_data (turma_id, data_aula),
  KEY idx_diario_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS diario_frequencias (
  id INT NOT NULL AUTO_INCREMENT,
  diario_aula_id INT NOT NULL,
  aluno_id INT NOT NULL,
  situacao ENUM('presente','falta','falta_justificada','atraso') NOT NULL DEFAULT 'presente',
  observacao VARCHAR(255) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_diario_aluno (diario_aula_id, aluno_id),
  KEY idx_diario_freq_aluno (aluno_id),
  KEY idx_diario_freq_situacao (situacao),
  CONSTRAINT fk_diario_freq_aula FOREIGN KEY (diario_aula_id) REFERENCES diario_aulas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
