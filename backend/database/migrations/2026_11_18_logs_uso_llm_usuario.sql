-- Quem estava logado em cada chamada de IA (relatório Custo LLM do master).
-- Tenant. Idempotente. Colunas nulas: chamadas antigas e backfill ficam sem usuário.

SET @db := DATABASE();

SET @tem_tabela := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'logs_uso_llm'
);

SET @tem_source := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'logs_uso_llm' AND COLUMN_NAME = 'source'
);
SET @depois := IF(@tem_source > 0, 'source', 'usage_type');

SET @tem_tipo := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'logs_uso_llm' AND COLUMN_NAME = 'usuario_tipo'
);
SET @sql := IF(@tem_tabela = 1 AND @tem_tipo = 0,
  CONCAT(
    'ALTER TABLE `logs_uso_llm` ADD COLUMN `usuario_tipo` VARCHAR(32) NULL DEFAULT NULL ',
    'COMMENT ''Perfil da sessão: aluno, professor, admin, pai, monitor'' AFTER `', @depois, '`'
  ),
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_id := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'logs_uso_llm' AND COLUMN_NAME = 'usuario_id'
);
SET @sql := IF(@tem_tabela = 1 AND @tem_id = 0,
  'ALTER TABLE `logs_uso_llm`
     ADD COLUMN `usuario_id` INT UNSIGNED NULL DEFAULT NULL
       COMMENT ''Id na tabela do perfil (alunos, professores ou usuarios). Sem FK: o id não é único entre perfis.''
     AFTER `usuario_tipo`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @tem_nome := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'logs_uso_llm' AND COLUMN_NAME = 'usuario_nome'
);
SET @sql := IF(@tem_tabela = 1 AND @tem_nome = 0,
  'ALTER TABLE `logs_uso_llm`
     ADD COLUMN `usuario_nome` VARCHAR(160) NULL DEFAULT NULL
       COMMENT ''Nome gravado na hora da chamada, para o detalhe do Custo LLM''
     AFTER `usuario_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
