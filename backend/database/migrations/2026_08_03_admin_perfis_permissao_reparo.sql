-- Reparo idempotente: admin_perfis_permissao + usuarios.perfil_permissao_id
-- Corrige tenants onde 2026_05_13_admin_perfis_permissao.sql foi registrada como
-- executada mas a coluna ativo (ou a tabela inteira) não foi aplicada — causa do
-- erro "Unknown column 'p.ativo'".
-- Rollback: 2026_08_03_admin_perfis_permissao_reparo_rollback.sql

SET @db := DATABASE();

-- 1) permissoes_admin_json (dependência opcional de posicionamento)
SET @has_perm_json := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'permissoes_admin_json'
);
SET @sql := IF(
  @has_perm_json > 0,
  'SELECT 1',
  'ALTER TABLE usuarios ADD COLUMN permissoes_admin_json JSON NULL COMMENT ''Permissões administrativas por módulo e ação'''
);
PREPARE stmt_perm_json FROM @sql;
EXECUTE stmt_perm_json;
DEALLOCATE PREPARE stmt_perm_json;

-- 2) Tabela completa (se ainda não existir)
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Coluna ativo (tabela existia incompleta)
SET @has_ativo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admin_perfis_permissao' AND COLUMN_NAME = 'ativo'
);
SET @sql := IF(
  @has_ativo > 0,
  'SELECT 1',
  'ALTER TABLE admin_perfis_permissao ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1'
);
PREPARE stmt_ativo FROM @sql;
EXECUTE stmt_ativo;
DEALLOCATE PREPARE stmt_ativo;

-- 4) Índice em ativo (se coluna existe mas índice não)
SET @has_ativo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admin_perfis_permissao' AND COLUMN_NAME = 'ativo'
);
SET @has_idx_ativo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admin_perfis_permissao' AND INDEX_NAME = 'idx_admin_perfis_permissao_ativo'
);
SET @sql := IF(
  @has_ativo = 0 OR @has_idx_ativo > 0,
  'SELECT 1',
  'ALTER TABLE admin_perfis_permissao ADD KEY idx_admin_perfis_permissao_ativo (ativo)'
);
PREPARE stmt_idx_ativo FROM @sql;
EXECUTE stmt_idx_ativo;
DEALLOCATE PREPARE stmt_idx_ativo;

-- 5) usuarios.perfil_permissao_id
SET @has_perfil_id := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'perfil_permissao_id'
);
SET @has_perm_json := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'permissoes_admin_json'
);
SET @sql := IF(
  @has_perfil_id > 0,
  'SELECT 1',
  IF(
    @has_perm_json > 0,
    'ALTER TABLE usuarios ADD COLUMN perfil_permissao_id INT NULL AFTER permissoes_admin_json',
    'ALTER TABLE usuarios ADD COLUMN perfil_permissao_id INT NULL'
  )
);
PREPARE stmt_perfil_id FROM @sql;
EXECUTE stmt_perfil_id;
DEALLOCATE PREPARE stmt_perfil_id;

-- 6) Índice em perfil_permissao_id
SET @has_perfil_id := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'perfil_permissao_id'
);
SET @has_idx_perfil := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'idx_usuarios_perfil_permissao_id'
);
SET @sql := IF(
  @has_perfil_id = 0 OR @has_idx_perfil > 0,
  'SELECT 1',
  'ALTER TABLE usuarios ADD KEY idx_usuarios_perfil_permissao_id (perfil_permissao_id)'
);
PREPARE stmt_idx_perfil FROM @sql;
EXECUTE stmt_idx_perfil;
DEALLOCATE PREPARE stmt_idx_perfil;

-- 7) FK usuarios -> admin_perfis_permissao
SET @has_table := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admin_perfis_permissao'
);
SET @has_perfil_id := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'perfil_permissao_id'
);
SET @has_fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND CONSTRAINT_NAME = 'fk_usuarios_perfil_permissao'
);
SET @sql := IF(
  @has_table = 0 OR @has_perfil_id = 0 OR @has_fk > 0,
  'SELECT 1',
  'ALTER TABLE usuarios ADD CONSTRAINT fk_usuarios_perfil_permissao FOREIGN KEY (perfil_permissao_id) REFERENCES admin_perfis_permissao(id) ON DELETE SET NULL'
);
PREPARE stmt_fk FROM @sql;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;
