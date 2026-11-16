-- Remove o modelo semeado da ata completa. Apaga também edição feita nesse código.
-- Rollback de 2026_11_16_resultado_ata_completa_layout.sql

SET @db := DATABASE();
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'secretaria_modelos_documentos');

SET @sql := IF(@has = 0, 'SELECT 1',
  "DELETE FROM `secretaria_modelos_documentos` WHERE `codigo` = 'resultado_ata_completa'");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
