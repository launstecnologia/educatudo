-- Histórico de alterações de notas lançadas (coordenação/professor).

CREATE TABLE IF NOT EXISTS provas_blocos_notas_lancadas_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bloco_id INT NOT NULL,
  professor_id INT NOT NULL,
  materia_id INT NOT NULL,
  turma_id INT NOT NULL,
  aluno_id INT NOT NULL,
  nota_anterior DECIMAL(6,2) DEFAULT NULL,
  nota_nova DECIMAL(6,2) DEFAULT NULL,
  observacao_anterior VARCHAR(500) DEFAULT NULL,
  observacao_nova VARCHAR(500) DEFAULT NULL,
  alterado_por INT DEFAULT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notas_log_bloco (bloco_id, criado_em),
  KEY idx_notas_log_aluno (aluno_id, bloco_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
