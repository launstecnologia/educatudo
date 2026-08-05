-- Rollback de 2026_08_03_admin_perfis_permissao_reparo.sql
-- Remove apenas colunas/índices/FK adicionados pelo reparo. Não dropa a tabela
-- admin_perfis_permissao (pode conter dados de produção).

SET @db := DATABASE();

SET @has_fk := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND CONSTRAINT_NAME = 'fk_usuarios_perfil_permissao'
);
SET @sql := IF(
  @has_fk = 0,
  'SELECT 1',
  'ALTER TABLE usuarios DROP FOREIGN KEY fk_usuarios_perfil_permissao'
);
PREPARE stmt_drop_fk FROM @sql;
EXECUTE stmt_drop_fk;
DEALLOCATE PREPARE stmt_drop_fk;

SET @has_idx_perfil := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND INDEX_NAME = 'idx_usuarios_perfil_permissao_id'
);
SET @sql := IF(
  @has_idx_perfil = 0,
  'SELECT 1',
  'ALTER TABLE usuarios DROP INDEX idx_usuarios_perfil_permissao_id'
);
PREPARE stmt_drop_idx_perfil FROM @sql;
EXECUTE stmt_drop_idx_perfil;
DEALLOCATE PREPARE stmt_drop_idx_perfil;

SET @has_perfil_id := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'perfil_permissao_id'
);
SET @sql := IF(
  @has_perfil_id = 0,
  'SELECT 1',
  'ALTER TABLE usuarios DROP COLUMN perfil_permissao_id'
);
PREPARE stmt_drop_perfil FROM @sql;
EXECUTE stmt_drop_perfil;
DEALLOCATE PREPARE stmt_drop_perfil;

SET @has_idx_ativo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admin_perfis_permissao' AND INDEX_NAME = 'idx_admin_perfis_permissao_ativo'
);
SET @sql := IF(
  @has_idx_ativo = 0,
  'SELECT 1',
  'ALTER TABLE admin_perfis_permissao DROP INDEX idx_admin_perfis_permissao_ativo'
);
PREPARE stmt_drop_idx_ativo FROM @sql;
EXECUTE stmt_drop_idx_ativo;
DEALLOCATE PREPARE stmt_drop_idx_ativo;

SET @has_table := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admin_perfis_permissao'
);
SET @has_ativo := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'admin_perfis_permissao' AND COLUMN_NAME = 'ativo'
);
SET @sql := IF(
  @has_table = 0 OR @has_ativo = 0,
  'SELECT 1',
  'ALTER TABLE admin_perfis_permissao DROP COLUMN ativo'
);
PREPARE stmt_drop_ativo FROM @sql;
EXECUTE stmt_drop_ativo;
DEALLOCATE PREPARE stmt_drop_ativo;
