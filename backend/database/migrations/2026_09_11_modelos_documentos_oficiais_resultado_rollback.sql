-- Reverse: 2026_09_11_modelos_documentos_oficiais_resultado.sql
-- DELETE justificado: remove só os dois códigos introduzidos por esta migration.
-- UPDATE de nomes volta ao rótulo anterior do catálogo.

SET @db := DATABASE();
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos');

SET @sql := IF(@has=0, 'SELECT 1',
  "DELETE FROM `secretaria_modelos_documentos`
    WHERE `codigo` IN ('resultado_certificado_conclusao', 'resultado_diploma')");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has=0, 'SELECT 1',
  "UPDATE `secretaria_modelos_documentos` SET `nome` = 'Boletim Escolar', `orientacao` = 'paisagem'
    WHERE `codigo` = 'resultado_boletim_padrao'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has=0, 'SELECT 1',
  "UPDATE `secretaria_modelos_documentos` SET `nome` = 'Ficha Individual', `orientacao` = 'retrato'
    WHERE `codigo` = 'resultado_ficha_individual'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has=0, 'SELECT 1',
  "UPDATE `secretaria_modelos_documentos` SET `nome` = 'Ata de Resultados Finais', `orientacao` = 'paisagem'
    WHERE `codigo` = 'resultado_ata_finais'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has=0, 'SELECT 1',
  "UPDATE `secretaria_modelos_documentos` SET `nome` = 'Histórico Escolar (Resultados)', `orientacao` = 'paisagem'
    WHERE `codigo` = 'resultado_historico'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@has=0, 'SELECT 1',
  "UPDATE `secretaria_modelos_documentos` SET `nome` = 'Declaração de Conclusão', `orientacao` = 'retrato'
    WHERE `codigo` = 'declaracao_conclusao'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
