-- ============================================================================
-- ROLLBACK - AVA / EAD Fase 3 (certificados)
-- ----------------------------------------------------------------------------
-- Remove a tabela criada em 2026_06_26_ava_05_fase3_certificados.sql.
-- NAO e executado automaticamente pelo runner (arquivos *_rollback.sql sao
-- ignorados); rode manualmente apenas para reverter.
-- ============================================================================

DROP TABLE IF EXISTS `ava_certificados`;
