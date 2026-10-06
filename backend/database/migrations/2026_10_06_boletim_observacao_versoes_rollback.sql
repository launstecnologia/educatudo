-- Rollback de 2026_10_06_boletim_observacao_versoes.sql
-- Remove versões e o log. O texto atual em boletim_observacoes permanece.

DROP TABLE IF EXISTS boletim_observacao_log;
DROP TABLE IF EXISTS boletim_observacao_versoes;
