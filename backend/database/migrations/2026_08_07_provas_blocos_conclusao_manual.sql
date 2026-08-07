-- Distingue conclusão manual (coordenação) da automática por horário,
-- para que reabrirSePrazoEstendido não desfaça marcação manual.
-- Tenant. Idempotente. Rollback: 2026_08_07_provas_blocos_conclusao_manual_rollback.sql

SET @db := DATABASE();
SET @has_pb := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos'
);
SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'provas_blocos' AND COLUMN_NAME = 'conclusao_manual'
);
SET @sql := IF(
    @has_pb > 0 AND @col = 0,
    "ALTER TABLE provas_blocos
       ADD COLUMN `conclusao_manual` TINYINT(1) NOT NULL DEFAULT 0
         COMMENT '1 = concluído manualmente pela coordenação; não reabrir por prazo'
         AFTER `status`",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
