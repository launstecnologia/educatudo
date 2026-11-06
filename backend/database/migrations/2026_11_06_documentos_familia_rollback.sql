-- Remove documentos da família, fichas de saúde, declarações e aceites.
-- Tenant. O DROP apaga os envios e os aceites de propósito.

DROP TABLE IF EXISTS `familia_declaracao_aceites`;
DROP TABLE IF EXISTS `familia_declaracoes`;
DROP TABLE IF EXISTS `familia_fichas_saude`;
DROP TABLE IF EXISTS `familia_documentos_arquivos`;
