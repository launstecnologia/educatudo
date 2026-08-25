-- Rollback: 2026_08_22_ocorrencias_ciclo.sql
-- Remove ciclo de vida/categoria/vínculo com Diário. Não apaga alunos_ocorrencias nem itens.
-- Justificativa do DROP: desfaz só o que esta migration adicionou.

SET @db := DATABASE();
SET @has_oc := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias');

SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ocorrencias_historico' AND CONSTRAINT_NAME='fk_ocorrencias_historico_ocorrencia' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk>0,
  "ALTER TABLE `ocorrencias_historico` DROP FOREIGN KEY `fk_ocorrencias_historico_ocorrencia`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `ocorrencias_historico`;

SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND CONSTRAINT_NAME='fk_ocorrencias_diario_aula' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@has_oc>0 AND @fk>0,
  "ALTER TABLE `alunos_ocorrencias` DROP FOREIGN KEY `fk_ocorrencias_diario_aula`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND CONSTRAINT_NAME='fk_ocorrencias_categoria' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@has_oc>0 AND @fk>0,
  "ALTER TABLE `alunos_ocorrencias` DROP FOREIGN KEY `fk_ocorrencias_categoria`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND INDEX_NAME='idx_ocorrencias_aula');
SET @sql := IF(@has_oc>0 AND @idx>0,
  "ALTER TABLE `alunos_ocorrencias` DROP KEY `idx_ocorrencias_aula`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND INDEX_NAME='idx_ocorrencias_turma');
SET @sql := IF(@has_oc>0 AND @idx>0,
  "ALTER TABLE `alunos_ocorrencias` DROP KEY `idx_ocorrencias_turma`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND INDEX_NAME='idx_ocorrencias_categoria');
SET @sql := IF(@has_oc>0 AND @idx>0,
  "ALTER TABLE `alunos_ocorrencias` DROP KEY `idx_ocorrencias_categoria`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND INDEX_NAME='idx_ocorrencias_status');
SET @sql := IF(@has_oc>0 AND @idx>0,
  "ALTER TABLE `alunos_ocorrencias` DROP KEY `idx_ocorrencias_status`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='encerrado_por');
SET @sql := IF(@has_oc>0 AND @col>0,
  "ALTER TABLE `alunos_ocorrencias` DROP COLUMN `encerrado_por`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='encerrado_em');
SET @sql := IF(@has_oc>0 AND @col>0,
  "ALTER TABLE `alunos_ocorrencias` DROP COLUMN `encerrado_em`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='responsavel_comunicado_em');
SET @sql := IF(@has_oc>0 AND @col>0,
  "ALTER TABLE `alunos_ocorrencias` DROP COLUMN `responsavel_comunicado_em`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='encaminhamento');
SET @sql := IF(@has_oc>0 AND @col>0,
  "ALTER TABLE `alunos_ocorrencias` DROP COLUMN `encaminhamento`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='local');
SET @sql := IF(@has_oc>0 AND @col>0,
  "ALTER TABLE `alunos_ocorrencias` DROP COLUMN `local`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='materia_id');
SET @sql := IF(@has_oc>0 AND @col>0,
  "ALTER TABLE `alunos_ocorrencias` DROP COLUMN `materia_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='diario_aula_id');
SET @sql := IF(@has_oc>0 AND @col>0,
  "ALTER TABLE `alunos_ocorrencias` DROP COLUMN `diario_aula_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='ano_letivo_id');
SET @sql := IF(@has_oc>0 AND @col>0,
  "ALTER TABLE `alunos_ocorrencias` DROP COLUMN `ano_letivo_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='turma_id');
SET @sql := IF(@has_oc>0 AND @col>0,
  "ALTER TABLE `alunos_ocorrencias` DROP COLUMN `turma_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='status');
SET @sql := IF(@has_oc>0 AND @col>0,
  "ALTER TABLE `alunos_ocorrencias` DROP COLUMN `status`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='categoria_id');
SET @sql := IF(@has_oc>0 AND @col>0,
  "ALTER TABLE `alunos_ocorrencias` DROP COLUMN `categoria_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `ocorrencias_categorias`;
