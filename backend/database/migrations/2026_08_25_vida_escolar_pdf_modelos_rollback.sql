-- Rollback: 2026_08_25_vida_escolar_pdf_modelos.sql
-- DELETE justificado: remove só os códigos introduzidos por esta migration.
-- Escolas que customizaram o HTML desses modelos perdem a customização no rollback.

SET @db := DATABASE();
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
  WHERE TABLE_SCHEMA=@db AND TABLE_NAME='secretaria_modelos_documentos');
SET @sql := IF(@has=0, 'SELECT 1',
  "DELETE FROM `secretaria_modelos_documentos`
    WHERE `codigo` IN (
      'vida_escolar_boletim',
      'vida_escolar_pacote',
      'vida_escolar_dossie',
      'vida_escolar_sed',
      'vida_escolar_historico'
    )");
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
