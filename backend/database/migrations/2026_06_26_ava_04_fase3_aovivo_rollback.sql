-- ============================================================================
-- ROLLBACK - AVA / EAD Fase 3 (aulas ao vivo)
-- ----------------------------------------------------------------------------
-- Remove a tabela criada em 2026_06_26_ava_04_fase3_aovivo.sql.
-- NAO e executado automaticamente pelo runner (arquivos *_rollback.sql sao
-- ignorados); rode manualmente apenas para reverter.
-- ============================================================================

DROP TABLE IF EXISTS `ava_aulas_ao_vivo`;
