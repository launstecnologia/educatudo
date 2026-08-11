-- Índices compostos para fluxo de jornadas (listagem, show, responder exercício).
-- Idempotente: só cria índice se a tabela existir e o índice ainda não existir.
-- Rollback: 2026_08_03_jornadas_perf_indices_rollback.sql

SET @db := DATABASE();

-- jornadas_progresso_alunos (aluno_id, jornada_id, atividade_tipo)
SET @has_table := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_progresso_alunos'
);
SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_progresso_alunos' AND INDEX_NAME = 'idx_jpa_aluno_jornada_tipo'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx > 0,
  'SELECT 1',
  'ALTER TABLE jornadas_progresso_alunos ADD KEY idx_jpa_aluno_jornada_tipo (aluno_id, jornada_id, atividade_tipo)'
);
PREPARE stmt_jpa1 FROM @sql;
EXECUTE stmt_jpa1;
DEALLOCATE PREPARE stmt_jpa1;

-- jornadas_progresso_alunos (aluno_id, modulo_id, atividade_tipo)
SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_progresso_alunos' AND INDEX_NAME = 'idx_jpa_aluno_modulo_tipo'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx > 0,
  'SELECT 1',
  'ALTER TABLE jornadas_progresso_alunos ADD KEY idx_jpa_aluno_modulo_tipo (aluno_id, modulo_id, atividade_tipo)'
);
PREPARE stmt_jpa2 FROM @sql;
EXECUTE stmt_jpa2;
DEALLOCATE PREPARE stmt_jpa2;

-- jornadas_progresso_alunos (aluno_id, exercicio_modulo_id, atividade_tipo)
SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_progresso_alunos' AND INDEX_NAME = 'idx_jpa_aluno_ex_modulo_tipo'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx > 0,
  'SELECT 1',
  'ALTER TABLE jornadas_progresso_alunos ADD KEY idx_jpa_aluno_ex_modulo_tipo (aluno_id, exercicio_modulo_id, atividade_tipo)'
);
PREPARE stmt_jpa3 FROM @sql;
EXECUTE stmt_jpa3;
DEALLOCATE PREPARE stmt_jpa3;

-- jornadas_modulos_exercicios (modulo_id, status)
SET @has_table := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_modulos_exercicios'
);
SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_modulos_exercicios' AND INDEX_NAME = 'idx_jme_modulo_status'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx > 0,
  'SELECT 1',
  'ALTER TABLE jornadas_modulos_exercicios ADD KEY idx_jme_modulo_status (modulo_id, status)'
);
PREPARE stmt_jme FROM @sql;
EXECUTE stmt_jme;
DEALLOCATE PREPARE stmt_jme;

-- jornadas_modulos_videos (modulo_id, status)
SET @has_table := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_modulos_videos'
);
SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_modulos_videos' AND INDEX_NAME = 'idx_jmv_modulo_status'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx > 0,
  'SELECT 1',
  'ALTER TABLE jornadas_modulos_videos ADD KEY idx_jmv_modulo_status (modulo_id, status)'
);
PREPARE stmt_jmv FROM @sql;
EXECUTE stmt_jmv;
DEALLOCATE PREPARE stmt_jmv;

-- jornadas_modulos_documentos (modulo_id, status)
SET @has_table := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_modulos_documentos'
);
SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_modulos_documentos' AND INDEX_NAME = 'idx_jmd_modulo_status'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx > 0,
  'SELECT 1',
  'ALTER TABLE jornadas_modulos_documentos ADD KEY idx_jmd_modulo_status (modulo_id, status)'
);
PREPARE stmt_jmd FROM @sql;
EXECUTE stmt_jmd;
DEALLOCATE PREPARE stmt_jmd;

-- jornadas_exercicios_auditoria (aluno_id, created_at) — tabela criada em runtime em alguns tenants
SET @has_table := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_exercicios_auditoria'
);
SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_exercicios_auditoria' AND INDEX_NAME = 'idx_jea_aluno_created'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx > 0,
  'SELECT 1',
  'ALTER TABLE jornadas_exercicios_auditoria ADD KEY idx_jea_aluno_created (aluno_id, created_at)'
);
PREPARE stmt_jea FROM @sql;
EXECUTE stmt_jea;
DEALLOCATE PREPARE stmt_jea;
