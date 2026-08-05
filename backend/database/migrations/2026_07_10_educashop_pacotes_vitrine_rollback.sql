-- Rollback: campos de vitrine EducaShop em pacotes_creditos.

ALTER TABLE `pacotes_creditos`
  DROP COLUMN IF EXISTS `ordem`,
  DROP COLUMN IF EXISTS `destaque`,
  DROP COLUMN IF EXISTS `imagem_url`,
  DROP COLUMN IF EXISTS `descricao`,
  DROP COLUMN IF EXISTS `categoria`;
