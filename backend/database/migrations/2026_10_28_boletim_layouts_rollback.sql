-- Rollback de 2026_10_28_boletim_layouts.sql
-- Remove o layout do boletim oficial. O demonstrativo de notas não usa esta tabela.

DROP TABLE IF EXISTS `boletim_layouts`;
