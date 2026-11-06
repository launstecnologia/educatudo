-- Remove só a declaração de exemplo, se o texto ainda for o fictício.
-- Se a escola já trocou o texto, a linha fica. Sem as tabelas, não faz nada.

SET @db := DATABASE();
SET @has := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME IN ('familia_declaracoes', 'familia_declaracao_aceites')
);
SET @sql := IF(@has=2,
  "DELETE a FROM familia_declaracao_aceites a
   INNER JOIN familia_declaracoes d ON d.id = a.declaracao_id
   WHERE d.titulo = 'Autorização de imagem (exemplo)'
     AND d.texto = 'Texto de exemplo para a família ver e aceitar no aplicativo. Ele não vale como documento da escola. Troque este texto no painel Documentos da família antes de pedir um aceite de verdade.'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has=2,
  "DELETE FROM familia_declaracoes
   WHERE titulo = 'Autorização de imagem (exemplo)'
     AND texto = 'Texto de exemplo para a família ver e aceitar no aplicativo. Ele não vale como documento da escola. Troque este texto no painel Documentos da família antes de pedir um aceite de verdade.'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
