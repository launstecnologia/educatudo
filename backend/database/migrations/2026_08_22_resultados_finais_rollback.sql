-- Rollback: 2026_08_22_resultados_finais.sql
-- Remove tabelas do fechamento acadêmico. Não dropa secretaria_modelos_documentos;
-- só apaga os códigos seedados desta feature, se a tabela existir.

SET @db := DATABASE();

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `resultado_documento_emissoes`;
DROP TABLE IF EXISTS `resultado_fechamento_config`;
DROP TABLE IF EXISTS `resultado_documento_layouts`;
DROP TABLE IF EXISTS `resultado_situacoes_especiais`;
DROP TABLE IF EXISTS `resultado_academico_historico`;
DROP TABLE IF EXISTS `resultado_academico_itens`;
DROP TABLE IF EXISTS `resultado_academico`;
SET FOREIGN_KEY_CHECKS = 1;

SET @has_mod := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos');
SET @sql := IF(@has_mod=0, 'SELECT 1',
  "DELETE FROM `secretaria_modelos_documentos`
    WHERE `codigo` IN (
      'resultado_ficha_individual',
      'resultado_ata_finais',
      'resultado_boletim_padrao',
      'resultado_relatorio_padrao',
      'resultado_historico'
    )");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
