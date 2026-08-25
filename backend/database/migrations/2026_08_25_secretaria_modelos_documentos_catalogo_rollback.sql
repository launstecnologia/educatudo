-- Rollback do catálogo de Layout de documentos.
-- Forward: 2026_08_25_secretaria_modelos_documentos_catalogo.sql
-- Apaga só os códigos que esta migration introduz de fato
-- (declaracao_historico, declaracao_bolsista_integral, declaracao_conclusao).
-- Não mexe em contratos/declarações de 2026_08_06 e 2026_08_22 nem nos
-- resultado_* de 2026_08_22_resultados_finais.sql (esta migration só faz
-- backfill com WHERE NOT EXISTS).
-- DELETE justificado: reverte o seed desta migration. Escolas que customizaram
-- o HTML desses três códigos perderiam a customização — esperado num rollback.

SET @db := DATABASE();
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos');
SET @sql := IF(@has=0, 'SELECT 1',
  "DELETE FROM `secretaria_modelos_documentos`
    WHERE `codigo` IN (
      'declaracao_historico',
      'declaracao_bolsista_integral',
      'declaracao_conclusao'
    )");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
