-- ============================================================================
-- ROLLBACK - AVA / EAD Fase 2
-- ----------------------------------------------------------------------------
-- Remove as tabelas criadas em 2026_06_26_ava_03_fase2.sql na ordem inversa
-- de dependencia. NAO e executado automaticamente pelo runner (arquivos
-- *_rollback.sql sao ignorados); rode manualmente apenas para reverter.
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `ava_comentarios`;
DROP TABLE IF EXISTS `ava_atividade_entrega_arquivos`;
DROP TABLE IF EXISTS `ava_atividade_entregas`;
DROP TABLE IF EXISTS `ava_atividades`;
DROP TABLE IF EXISTS `ava_rubrica_criterios`;
DROP TABLE IF EXISTS `ava_rubricas`;

SET FOREIGN_KEY_CHECKS = 1;
