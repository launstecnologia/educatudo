-- Expo Colag: lista de materiais do almoxarifado e flag EducaLabs no projeto.
-- Tenant. Idempotente. Rollback: 2026_08_29_expo_colag_recursos_rollback.sql

SET @db := DATABASE();

SET @tem := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='expo_colag_projetos');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='expo_colag_projetos' AND COLUMN_NAME='materiais_necessarios');
SET @sql := IF(@tem>0 AND @col=0,
  "ALTER TABLE `expo_colag_projetos` ADD COLUMN `materiais_necessarios` TEXT NULL COMMENT 'JSON: itens para autorização/almoxarifado' AFTER `custo_tudicoins`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='expo_colag_projetos' AND COLUMN_NAME='educalabs_ativa');
SET @sql := IF(@tem>0 AND @col=0,
  "ALTER TABLE `expo_colag_projetos` ADD COLUMN `educalabs_ativa` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tudinha_ativa`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
