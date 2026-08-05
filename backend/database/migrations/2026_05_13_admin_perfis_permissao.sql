CREATE TABLE IF NOT EXISTS admin_perfis_permissao (
  id INT NOT NULL AUTO_INCREMENT,
  nome VARCHAR(120) NOT NULL,
  tipo_base ENUM('dev','diretor','coordenador','financeiro','secretaria') NOT NULL,
  descricao VARCHAR(255) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  permissoes_json JSON NOT NULL,
  criado_por INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_admin_perfis_permissao_nome (nome),
  KEY idx_admin_perfis_permissao_tipo_base (tipo_base),
  KEY idx_admin_perfis_permissao_ativo (ativo),
  KEY idx_admin_perfis_permissao_criado_por (criado_por),
  CONSTRAINT fk_admin_perfis_permissao_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

ALTER TABLE usuarios
  ADD COLUMN perfil_permissao_id INT NULL AFTER permissoes_admin_json,
  ADD KEY idx_usuarios_perfil_permissao_id (perfil_permissao_id),
  ADD CONSTRAINT fk_usuarios_perfil_permissao FOREIGN KEY (perfil_permissao_id) REFERENCES admin_perfis_permissao(id) ON DELETE SET NULL;
