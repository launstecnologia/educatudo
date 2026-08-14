-- Rollback do upgrade matricula_processos.
-- Remove satélites, DROP COLUMN das colunas novas e rename de volta para enrollment*.
-- ATENÇÃO: DROP dos satélites apaga dados de responsáveis/produtos/documentos do processo.

SET @db := DATABASE();

DROP TABLE IF EXISTS `matricula_processos_documentos`;
DROP TABLE IF EXISTS `matricula_processos_produtos`;
DROP TABLE IF EXISTS `matricula_processos_responsaveis`;

-- Resolve nome atual da tabela principal
SET @tbl := IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos') > 0,
  'matricula_processos',
  IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='enrollment') > 0,
    'enrollment',
    NULL
  )
);

-- DROP COLUMN protegido (só se a tabela existir)
SET @cols := 'zapsign_enviado_em,zapsign_status,zapsign_sign_url,zapsign_signer_token,zapsign_doc_token,dados_confirmados_em,documento_assinatura_codigo,pagante_modo,pagamento_status,finance_cobrancas,finance_plan_id,aluno_endereco,aluno_rg';

-- zapsign_enviado_em
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='zapsign_enviado_em');
SET @sql := IF(@tbl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tbl, '` DROP COLUMN zapsign_enviado_em'), 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='zapsign_status');
SET @sql := IF(@tbl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tbl, '` DROP COLUMN zapsign_status'), 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='zapsign_sign_url');
SET @sql := IF(@tbl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tbl, '` DROP COLUMN zapsign_sign_url'), 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='zapsign_signer_token');
SET @sql := IF(@tbl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tbl, '` DROP COLUMN zapsign_signer_token'), 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='zapsign_doc_token');
SET @sql := IF(@tbl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tbl, '` DROP COLUMN zapsign_doc_token'), 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='dados_confirmados_em');
SET @sql := IF(@tbl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tbl, '` DROP COLUMN dados_confirmados_em'), 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='documento_assinatura_codigo');
SET @sql := IF(@tbl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tbl, '` DROP COLUMN documento_assinatura_codigo'), 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='pagante_modo');
SET @sql := IF(@tbl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tbl, '` DROP COLUMN pagante_modo'), 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='pagamento_status');
SET @sql := IF(@tbl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tbl, '` DROP COLUMN pagamento_status'), 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='finance_cobrancas');
SET @sql := IF(@tbl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tbl, '` DROP COLUMN finance_cobrancas'), 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='finance_plan_id');
SET @sql := IF(@tbl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tbl, '` DROP COLUMN finance_plan_id'), 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='aluno_endereco');
SET @sql := IF(@tbl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tbl, '` DROP COLUMN aluno_endereco'), 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME=@tbl AND COLUMN_NAME='aluno_rg');
SET @sql := IF(@tbl IS NOT NULL AND @col>0, CONCAT('ALTER TABLE `', @tbl, '` DROP COLUMN aluno_rg'), 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Rename de volta (só se enrollment não existir)
SET @has_mp := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matricula_processos'
);
SET @has_enr := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'enrollment'
);
SET @sql := IF(@has_mp > 0 AND @has_enr = 0,
  'RENAME TABLE `matricula_processos` TO `enrollment`',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_audit_new := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matricula_processos_auditorias'
);
SET @has_audit_old := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'enrollment_audit'
);
SET @sql := IF(@has_audit_new > 0 AND @has_audit_old = 0,
  'RENAME TABLE `matricula_processos_auditorias` TO `enrollment_audit`',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_score_new := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'matricula_processos_scores'
);
SET @has_score_old := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'enrollment_score'
);
SET @sql := IF(@has_score_new > 0 AND @has_score_old = 0,
  'RENAME TABLE `matricula_processos_scores` TO `enrollment_score`',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
