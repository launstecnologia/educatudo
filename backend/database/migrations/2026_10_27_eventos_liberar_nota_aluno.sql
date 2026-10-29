-- Evento avaliativo pode liberar a nota para o aluno assim que ela é lançada.
-- A média e o snapshot do boletim continuam na geração do período.
-- Tenant. Sem escola_id. Idempotente.
-- Rollback: 2026_10_27_eventos_liberar_nota_aluno_rollback.sql

SET @db := DATABASE();
SET @col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db
    AND TABLE_NAME = 'eventos_avaliativos'
    AND COLUMN_NAME = 'liberar_nota_aluno'
);
SET @sql := IF(
  @col = 0,
  'ALTER TABLE `eventos_avaliativos`
     ADD COLUMN `liberar_nota_aluno` TINYINT(1) NOT NULL DEFAULT 0
       COMMENT ''1 = aluno vê a nota assim que ela é lançada''
     AFTER `ativo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
