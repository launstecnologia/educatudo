-- Formato do evento: online (questões) vs lançamento de nota pelo professor
-- Execute manualmente no MySQL do tenant. Se aparecer erro de coluna duplicada, a coluna já existe.
-- Depois rode o CREATE TABLE (pode repetir com IF NOT EXISTS).

ALTER TABLE provas_blocos
  ADD COLUMN formato_evento VARCHAR(32) NOT NULL DEFAULT 'online_questoes'
  COMMENT 'online_questoes=prova com questões; lancamento_nota=professor lança nota por aluno/turma'
  AFTER tipo_prova;

CREATE TABLE IF NOT EXISTS provas_blocos_notas_lancadas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  bloco_id INT NOT NULL,
  professor_id INT NOT NULL,
  materia_id INT NOT NULL,
  turma_id INT NOT NULL,
  aluno_id INT NOT NULL,
  nota DECIMAL(6,2) DEFAULT NULL,
  observacao VARCHAR(500) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bloco_prof_mat_tur_aluno (bloco_id, professor_id, materia_id, turma_id, aluno_id),
  KEY idx_bloco_prof_mat (bloco_id, professor_id, materia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Também execute (se ainda não rodou): 2026_04_17_provas_blocos_ano_letivo_bimestre.sql
