-- Mesmo conteúdo que src/database/migrations/058_monitores_perfil.sql

CREATE TABLE IF NOT EXISTS monitores (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nome VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL,
  senha_hash VARCHAR(255) NOT NULL,
  turmas JSON NULL COMMENT 'IDs das turmas que o monitor pode acompanhar',
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_monitores_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE alunos_sessoes_acesso
  ADD COLUMN ultima_atividade_at DATETIME NULL DEFAULT NULL AFTER login_at,
  ADD COLUMN contexto_tipo VARCHAR(50) NULL DEFAULT NULL AFTER ultima_atividade_at,
  ADD COLUMN contexto_id INT UNSIGNED NULL DEFAULT NULL AFTER contexto_tipo,
  ADD COLUMN contexto_label VARCHAR(500) NULL DEFAULT NULL AFTER contexto_id;

CREATE TABLE IF NOT EXISTS monitor_acoes_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  monitor_id INT UNSIGNED NOT NULL,
  aluno_id INT UNSIGNED NULL DEFAULT NULL,
  acao VARCHAR(100) NOT NULL,
  detalhes JSON NULL,
  ip_address VARCHAR(45) NULL DEFAULT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_monitor_acoes_monitor (monitor_id),
  KEY idx_monitor_acoes_aluno (aluno_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
