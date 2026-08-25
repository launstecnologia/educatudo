-- Rollback da 2026_08_15_matricula_secretaria_ciclo.sql
-- DROP justificado: reverte tabelas e colunas introduzidas nesta migration.

SET @db := DATABASE();

DROP TABLE IF EXISTS `matricula_transferencias`;
DROP TABLE IF EXISTS `matricula_checklist_itens`;
DROP TABLE IF EXISTS `matricula_campanha_planos`;
DROP TABLE IF EXISTS `matricula_campanhas`;

SET @has_fp := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='finance_plans');
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='finance_plans' AND COLUMN_NAME='plano_origem_id');
SET @sql := IF(@has_fp>0 AND @col>0, "ALTER TABLE finance_plans DROP COLUMN `plano_origem_id`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_mp := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='aluno_nacionalidade');
SET @sql := IF(@has_mp>0 AND @col>0,
  "ALTER TABLE matricula_processos
     DROP COLUMN `aluno_nacionalidade`,
     DROP COLUMN `aluno_cor_raca`,
     DROP COLUMN `aluno_codigo_inep`,
     DROP COLUMN `aluno_nome_pai`,
     DROP COLUMN `aluno_nome_mae`",
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='reserva_ate');
SET @sql := IF(@has_mp>0 AND @col>0, "ALTER TABLE matricula_processos DROP COLUMN `reserva_ate`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='entrou_fila_em');
SET @sql := IF(@has_mp>0 AND @col>0, "ALTER TABLE matricula_processos DROP COLUMN `entrou_fila_em`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='fila_posicao');
SET @sql := IF(@has_mp>0 AND @col>0, "ALTER TABLE matricula_processos DROP COLUMN `fila_posicao`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula_processos' AND COLUMN_NAME='campanha_id');
SET @sql := IF(@has_mp>0 AND @col>0, "ALTER TABLE matricula_processos DROP COLUMN `campanha_id`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_mat := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula');
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matricula' AND COLUMN_NAME='resultado_ano');
SET @sql := IF(@has_mat>0 AND @col>0, "ALTER TABLE matricula DROP COLUMN `resultado_ano`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @has_turmas := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas');
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND COLUMN_NAME='turma_origem_id');
SET @sql := IF(@has_turmas>0 AND @col>0, "ALTER TABLE turmas DROP COLUMN `turma_origem_id`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND COLUMN_NAME='vagas');
SET @sql := IF(@has_turmas>0 AND @col>0, "ALTER TABLE turmas DROP COLUMN `vagas`", 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
