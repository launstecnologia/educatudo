-- Rollback da vida escolar. Tenant.
-- DROP na ordem inversa das FKs.

DROP TABLE IF EXISTS `vida_escolar_importacoes`;
DROP TABLE IF EXISTS `vida_escolar_documentos`;
DROP TABLE IF EXISTS `escolarizacao_componentes`;
DROP TABLE IF EXISTS `escolarizacao_anos`;
DROP TABLE IF EXISTS `boletim_ficha_auditoria`;
DROP TABLE IF EXISTS `boletim_ficha_celulas`;
DROP TABLE IF EXISTS `boletim_ficha_linhas`;
DROP TABLE IF EXISTS `boletim_fichas`;
