-- Diário de Classe: tipo da aula e vínculo opcional com Evento de Prova/Nota.
-- Não altera faltas_eventos, faltas_lancamentos, boletim_* nem planos_aula.
-- Colunas novas e opcionais — aulas existentes recebem tipo_aula = 'regular'.
-- Tenant. Idempotente. Rollback: 2026_08_22_diario_aula_tipo_evento_rollback.sql

SET @db := DATABASE();

-- diario_aulas.tipo_aula
SET @has_aulas := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_aulas');
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_aulas' AND COLUMN_NAME='tipo_aula');
SET @sql := IF(@has_aulas>0 AND @col=0,
  "ALTER TABLE `diario_aulas` ADD COLUMN `tipo_aula` ENUM('regular','avaliacao','revisao','recuperacao','atividade','projeto','evento_escolar','reposicao') NOT NULL DEFAULT 'regular' AFTER `observacoes`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- diario_aulas.evento_bloco_id (FK lógica para provas_blocos.id; SET NULL se o evento for apagado)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_aulas' AND COLUMN_NAME='evento_bloco_id');
SET @sql := IF(@has_aulas>0 AND @col=0,
  "ALTER TABLE `diario_aulas` ADD COLUMN `evento_bloco_id` INT NULL DEFAULT NULL AFTER `plano_aula_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_aulas' AND INDEX_NAME='idx_diario_evento_bloco');
SET @sql := IF(@has_aulas>0 AND @idx=0,
  "ALTER TABLE `diario_aulas` ADD KEY `idx_diario_evento_bloco` (`evento_bloco_id`)",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_blocos := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='provas_blocos');
SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_aulas' AND CONSTRAINT_NAME='fk_diario_aulas_evento_bloco' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@has_aulas>0 AND @has_blocos>0 AND @fk=0,
  "ALTER TABLE `diario_aulas` ADD CONSTRAINT `fk_diario_aulas_evento_bloco` FOREIGN KEY (`evento_bloco_id`) REFERENCES `provas_blocos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
