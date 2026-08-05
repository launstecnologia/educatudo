-- Tenant: campos opcionais de vitrine EducaShop em pacotes_creditos.

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacotes_creditos' AND COLUMN_NAME = 'categoria'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `pacotes_creditos` ADD COLUMN `categoria` VARCHAR(32) NULL DEFAULT NULL COMMENT ''inicio|intermediario|premium'' AFTER `nome`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacotes_creditos' AND COLUMN_NAME = 'descricao'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `pacotes_creditos` ADD COLUMN `descricao` TEXT NULL DEFAULT NULL AFTER `categoria`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacotes_creditos' AND COLUMN_NAME = 'imagem_url'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `pacotes_creditos` ADD COLUMN `imagem_url` VARCHAR(512) NULL DEFAULT NULL AFTER `descricao`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacotes_creditos' AND COLUMN_NAME = 'destaque'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `pacotes_creditos` ADD COLUMN `destaque` TINYINT(1) NOT NULL DEFAULT 0 AFTER `imagem_url`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pacotes_creditos' AND COLUMN_NAME = 'ordem'
);
SET @sql := IF(@col_exists = 0,
  'ALTER TABLE `pacotes_creditos` ADD COLUMN `ordem` INT NOT NULL DEFAULT 0 AFTER `destaque`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
