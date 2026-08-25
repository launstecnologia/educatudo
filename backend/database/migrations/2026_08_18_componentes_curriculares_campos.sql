-- Componentes Curriculares (rename de "Matérias" na camada de aplicação): novos campos
-- na tabela materias — código, sigla, área do conhecimento, tipo, descrição, cor,
-- ordem de exibição, flags de permissão (avaliação/frequência/plano de aula/diário)
-- e situação (ativo).
-- Tenant. Idempotente. Rollback: 2026_08_18_componentes_curriculares_campos_rollback.sql
--
-- ATENÇÃO: este arquivo já rodou com sucesso em pelo menos uma escola (Colag).
-- NÃO editar mais este conteúdo — o painel Master rastreia migration executada
-- por NOME de arquivo, não por hash de conteúdo, então qualquer alteração feita
-- aqui depois da primeira execução real NUNCA chega às escolas que já rodaram
-- esta versão. Campos adicionados depois desta versão original (aplicabilidade
-- por etapa de ensino, ampliação dos ENUMs de área/tipo) vivem em
-- 2026_08_18_componentes_curriculares_etapas.sql, um arquivo novo.
--
-- Observação: a tabela física continua se chamando `materias` (usada como FK em
-- ~10 tabelas core: provas, jornadas, grade_horaria, planos_aula, diário,
-- ava_disciplinas, mural_recados etc.) — só a camada de aplicação foi renomeada
-- para "Componentes Curriculares". Todas as colunas novas têm DEFAULT para não
-- quebrar linhas já existentes em nenhuma escola.

SET @db := DATABASE();
SET @has_materias := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='codigo');
SET @sql := IF(@has_materias>0 AND @col=0,
  "ALTER TABLE materias ADD COLUMN `codigo` VARCHAR(20) NOT NULL DEFAULT '' AFTER `nome`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='sigla');
SET @sql := IF(@has_materias>0 AND @col=0,
  "ALTER TABLE materias ADD COLUMN `sigla` VARCHAR(10) NULL DEFAULT NULL AFTER `codigo`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='area_conhecimento');
SET @sql := IF(@has_materias>0 AND @col=0,
  "ALTER TABLE materias ADD COLUMN `area_conhecimento` ENUM('linguagens','matematica','ciencias_natureza','ciencias_humanas','outra') NOT NULL DEFAULT 'outra' AFTER `sigla`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='tipo');
SET @sql := IF(@has_materias>0 AND @col=0,
  "ALTER TABLE materias ADD COLUMN `tipo` ENUM('formacao_geral','eletiva','itinerario_formativo','extracurricular','outra') NOT NULL DEFAULT 'formacao_geral' AFTER `area_conhecimento`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='descricao');
SET @sql := IF(@has_materias>0 AND @col=0,
  "ALTER TABLE materias ADD COLUMN `descricao` TEXT NULL AFTER `tipo`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='cor');
SET @sql := IF(@has_materias>0 AND @col=0,
  "ALTER TABLE materias ADD COLUMN `cor` VARCHAR(7) NULL DEFAULT '#3B82F6' AFTER `descricao`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='ordem');
SET @sql := IF(@has_materias>0 AND @col=0,
  "ALTER TABLE materias ADD COLUMN `ordem` INT NOT NULL DEFAULT 0 AFTER `cor`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='permite_avaliacao');
SET @sql := IF(@has_materias>0 AND @col=0,
  "ALTER TABLE materias ADD COLUMN `permite_avaliacao` TINYINT(1) NOT NULL DEFAULT 1 AFTER `ordem`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='permite_frequencia');
SET @sql := IF(@has_materias>0 AND @col=0,
  "ALTER TABLE materias ADD COLUMN `permite_frequencia` TINYINT(1) NOT NULL DEFAULT 1 AFTER `permite_avaliacao`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='permite_plano_aula');
SET @sql := IF(@has_materias>0 AND @col=0,
  "ALTER TABLE materias ADD COLUMN `permite_plano_aula` TINYINT(1) NOT NULL DEFAULT 1 AFTER `permite_frequencia`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='permite_diario');
SET @sql := IF(@has_materias>0 AND @col=0,
  "ALTER TABLE materias ADD COLUMN `permite_diario` TINYINT(1) NOT NULL DEFAULT 1 AFTER `permite_plano_aula`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='materias' AND COLUMN_NAME='ativo');
SET @sql := IF(@has_materias>0 AND @col=0,
  "ALTER TABLE materias ADD COLUMN `ativo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `permite_diario`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
