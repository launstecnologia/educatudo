-- Rollback do papel timbrado e seeds de declarações.
-- Forward: 2026_08_22_secretaria_layout_documentos.sql
-- Apaga só os códigos semeados por esta migration (não mexe em contratos/resultado_*).
-- DELETE justificado: reverte o seed desta migration; escolas que customizaram o HTML
-- desses códigos perderiam a customização — esperado num rollback de seed.

SET @db := DATABASE();
SET @has_mod := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos');
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "DELETE FROM `secretaria_modelos_documentos`
    WHERE `codigo` IN (
      'declaracao_matricula',
      'declaracao_frequencia',
      'declaracao_comparecimento',
      'declaracao_transferencia',
      'declaracao_ficha_matricula',
      'declaracao_aut_saida',
      'declaracao_aut_retirada',
      'declaracao_aut_imagem',
      'declaracao_aut_passeio'
    )");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TABLE IF EXISTS `secretaria_declaracoes_layouts`;
