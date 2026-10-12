-- Rollback de 2026_10_02_documentos_emissoes.sql
-- Remove o índice das versões. Os PDFs em documentos/emissoes permanecem no storage.

DROP TABLE IF EXISTS `documentos_emissoes`;
