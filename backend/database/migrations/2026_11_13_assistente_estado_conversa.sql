-- Estado estruturado da conversa do Assistente (motor v2): aluno em foco, fluxo e rascunho.
-- Tenant. Idempotente. Rollback: 2026_11_13_assistente_estado_conversa_rollback.sql

SET @db := DATABASE();

SET @tem_tabela := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_conversas'
);
SET @tem_col := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'assistente_conversas' AND COLUMN_NAME = 'estado_json'
);
SET @sql := IF(@tem_tabela = 1 AND @tem_col = 0,
  'ALTER TABLE `assistente_conversas`
     ADD COLUMN `estado_json` JSON NULL
       COMMENT ''Estado do motor v2: aluno_ativo, fluxo, rascunho, pendente''
     AFTER `aluno_nome`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
