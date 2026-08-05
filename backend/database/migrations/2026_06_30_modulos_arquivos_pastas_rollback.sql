ALTER TABLE `modulos_arquivos` DROP KEY IF EXISTS `idx_pasta_id`;
ALTER TABLE `modulos_arquivos` DROP COLUMN IF EXISTS `pasta_id`;
DROP TABLE IF EXISTS `modulos_arquivos_pastas`;
