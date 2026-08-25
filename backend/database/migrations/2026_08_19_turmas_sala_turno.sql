-- Turma: campos Turno, Sala Padrão e Observações. Reaproveita a tabela
-- `school_locations` (já usada pelo Patrimônio) como cadastro de
-- Salas/Ambientes — só adiciona a coluna `capacidade` que faltava.
-- Tenant. Idempotente. Rollback: 2026_08_19_turmas_sala_turno_rollback.sql

SET @db := DATABASE();

-- school_locations.capacidade
SET @has_locations := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='school_locations');
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='school_locations' AND COLUMN_NAME='capacidade');
SET @sql := IF(@has_locations>0 AND @col=0,
  "ALTER TABLE `school_locations` ADD COLUMN `capacidade` INT UNSIGNED DEFAULT NULL AFTER `tipo`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- turmas.turno
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND COLUMN_NAME='turno');
SET @sql := IF(@col=0,
  "ALTER TABLE `turmas` ADD COLUMN `turno` VARCHAR(20) DEFAULT NULL",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- turmas.sala_padrao_id
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND COLUMN_NAME='sala_padrao_id');
SET @sql := IF(@col=0,
  "ALTER TABLE `turmas` ADD COLUMN `sala_padrao_id` INT DEFAULT NULL",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- turmas.observacoes
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND COLUMN_NAME='observacoes');
SET @sql := IF(@col=0,
  "ALTER TABLE `turmas` ADD COLUMN `observacoes` TEXT DEFAULT NULL",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- FK turmas.sala_padrao_id -> school_locations.id (só se a tabela de salas existir)
SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND CONSTRAINT_NAME='fk_turmas_sala_padrao' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@fk=0 AND @has_locations>0,
  "ALTER TABLE `turmas` ADD CONSTRAINT `fk_turmas_sala_padrao` FOREIGN KEY (`sala_padrao_id`) REFERENCES `school_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
