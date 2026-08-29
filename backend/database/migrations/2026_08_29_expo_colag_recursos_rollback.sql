-- Rollback de 2026_08_29_expo_colag_recursos.sql
-- Remove colunas novas de expo_colag_projetos. Dados desses campos são descartados.

SET @db := DATABASE();
SET @tem := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='expo_colag_projetos');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='expo_colag_projetos' AND COLUMN_NAME='materiais_necessarios');
SET @sql := IF(@tem>0 AND @col>0,
  'ALTER TABLE `expo_colag_projetos` DROP COLUMN `materiais_necessarios`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='expo_colag_projetos' AND COLUMN_NAME='educalabs_ativa');
SET @sql := IF(@tem>0 AND @col>0,
  'ALTER TABLE `expo_colag_projetos` DROP COLUMN `educalabs_ativa`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
