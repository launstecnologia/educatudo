-- Rollback de 2026_10_05_validacao_tokens_user_tipo.sql
-- Remove o perfil gravado no token. Tokens já emitidos continuam válidos;
-- a validação volta a inferir aluno/professor só pelo app.

SET @col_user_tipo_exists := (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'validacao_tokens_apps'
      AND column_name = 'user_tipo'
);
SET @sql_drop_user_tipo := IF(
    @col_user_tipo_exists > 0,
    'ALTER TABLE validacao_tokens_apps DROP COLUMN user_tipo',
    'SELECT 1'
);
PREPARE stmt_drop_user_tipo FROM @sql_drop_user_tipo;
EXECUTE stmt_drop_user_tipo;
DEALLOCATE PREPARE stmt_drop_user_tipo;
