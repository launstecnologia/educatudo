-- Grava o perfil de quem emitiu o token de app externo.
-- Sem isso, a validação trata todo app que não é Educa Prof como aluno
-- e pode debitar a carteira ou montar o contexto do aluno com o mesmo id numérico.

SET @col_user_tipo_exists := (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'validacao_tokens_apps'
      AND column_name = 'user_tipo'
);
SET @sql_col_user_tipo := IF(
    @col_user_tipo_exists = 0,
    'ALTER TABLE validacao_tokens_apps ADD COLUMN user_tipo VARCHAR(20) NULL DEFAULT NULL AFTER user_name',
    'SELECT 1'
);
PREPARE stmt_col_user_tipo FROM @sql_col_user_tipo;
EXECUTE stmt_col_user_tipo;
DEALLOCATE PREPARE stmt_col_user_tipo;
