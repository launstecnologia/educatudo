-- Chat das aulas online. A migration 2026_05_28_aulas_online_chat.sql já existia,
-- mas em tenants antigos (ex.: XDQ) foi marcada como executada sem criar a tabela.
-- Idempotente: não falha se a tabela já existir.
CREATE TABLE IF NOT EXISTS aulas_online_chat_messages (
  id INT NOT NULL AUTO_INCREMENT,
  aula_online_id INT NOT NULL,
  user_id INT NOT NULL,
  user_tipo VARCHAR(30) NOT NULL,
  user_nome VARCHAR(140) NOT NULL,
  mensagem TEXT NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_aulas_online_chat_aula (aula_online_id),
  KEY idx_aulas_online_chat_created (created_at),
  KEY idx_aulas_online_chat_ativo (ativo),
  CONSTRAINT fk_aulas_online_chat_aula
    FOREIGN KEY (aula_online_id) REFERENCES aulas_online(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
