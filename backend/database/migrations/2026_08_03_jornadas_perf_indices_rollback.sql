-- Rollback: 2026_08_03_jornadas_perf_indices.sql

SET @db := DATABASE();

-- jornadas_progresso_alunos
SET @has_table := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_progresso_alunos'
);
SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_progresso_alunos' AND INDEX_NAME = 'idx_jpa_aluno_jornada_tipo'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx = 0,
  'SELECT 1',
  'ALTER TABLE jornadas_progresso_alunos DROP INDEX idx_jpa_aluno_jornada_tipo'
);
PREPARE stmt_jpa1 FROM @sql;
EXECUTE stmt_jpa1;
DEALLOCATE PREPARE stmt_jpa1;

SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_progresso_alunos' AND INDEX_NAME = 'idx_jpa_aluno_modulo_tipo'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx = 0,
  'SELECT 1',
  'ALTER TABLE jornadas_progresso_alunos DROP INDEX idx_jpa_aluno_modulo_tipo'
);
PREPARE stmt_jpa2 FROM @sql;
EXECUTE stmt_jpa2;
DEALLOCATE PREPARE stmt_jpa2;

SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_progresso_alunos' AND INDEX_NAME = 'idx_jpa_aluno_ex_modulo_tipo'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx = 0,
  'SELECT 1',
  'ALTER TABLE jornadas_progresso_alunos DROP INDEX idx_jpa_aluno_ex_modulo_tipo'
);
PREPARE stmt_jpa3 FROM @sql;
EXECUTE stmt_jpa3;
DEALLOCATE PREPARE stmt_jpa3;

-- jornadas_modulos_exercicios
SET @has_table := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_modulos_exercicios'
);
SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_modulos_exercicios' AND INDEX_NAME = 'idx_jme_modulo_status'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx = 0,
  'SELECT 1',
  'ALTER TABLE jornadas_modulos_exercicios DROP INDEX idx_jme_modulo_status'
);
PREPARE stmt_jme FROM @sql;
EXECUTE stmt_jme;
DEALLOCATE PREPARE stmt_jme;

-- jornadas_modulos_videos
SET @has_table := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_modulos_videos'
);
SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_modulos_videos' AND INDEX_NAME = 'idx_jmv_modulo_status'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx = 0,
  'SELECT 1',
  'ALTER TABLE jornadas_modulos_videos DROP INDEX idx_jmv_modulo_status'
);
PREPARE stmt_jmv FROM @sql;
EXECUTE stmt_jmv;
DEALLOCATE PREPARE stmt_jmv;

-- jornadas_modulos_documentos
SET @has_table := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_modulos_documentos'
);
SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_modulos_documentos' AND INDEX_NAME = 'idx_jmd_modulo_status'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx = 0,
  'SELECT 1',
  'ALTER TABLE jornadas_modulos_documentos DROP INDEX idx_jmd_modulo_status'
);
PREPARE stmt_jmd FROM @sql;
EXECUTE stmt_jmd;
DEALLOCATE PREPARE stmt_jmd;

-- jornadas_exercicios_auditoria
SET @has_table := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_exercicios_auditoria'
);
SET @has_idx := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'jornadas_exercicios_auditoria' AND INDEX_NAME = 'idx_jea_aluno_created'
);
SET @sql := IF(
  @has_table = 0 OR @has_idx = 0,
  'SELECT 1',
  'ALTER TABLE jornadas_exercicios_auditoria DROP INDEX idx_jea_aluno_created'
);
PREPARE stmt_jea FROM @sql;
EXECUTE stmt_jea;
DEALLOCATE PREPARE stmt_jea;
