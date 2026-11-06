-- Declaração de exemplo para a família aceitar no aplicativo.
-- O texto é fictício e a escola pode editar no painel.
-- Tenant. Idempotente. Rollback: 2026_11_07_declaracao_exemplo_familia_rollback.sql

SET @db := DATABASE();
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='familia_declaracoes');
SET @sql := IF(@has=1,
  "INSERT INTO familia_declaracoes (titulo, texto, versao, ativa)
   SELECT 'Autorização de imagem (exemplo)',
          'Texto de exemplo para a família ver e aceitar no aplicativo. Ele não vale como documento da escola. Troque este texto no painel Documentos da família antes de pedir um aceite de verdade.',
          1, 1
   FROM DUAL
   WHERE NOT EXISTS (SELECT 1 FROM familia_declaracoes WHERE titulo = 'Autorização de imagem (exemplo)')",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
