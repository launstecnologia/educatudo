-- Informação particular do professor no calendário letivo.
-- Não entra na contagem de dias letivos nem na visão de aluno, pais ou admin.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_11_17_calendario_informacao_professor_rollback.sql

SET @db := DATABASE();
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'calendario_letivo_eventos'
    AND COLUMN_NAME = 'professor_id'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `calendario_letivo_eventos`
     ADD COLUMN `professor_id` INT(11) NULL DEFAULT NULL
       COMMENT ''Professor dono da informação particular''
     AFTER `calendario_id`,
     ADD KEY `idx_cal_eventos_professor` (`professor_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
