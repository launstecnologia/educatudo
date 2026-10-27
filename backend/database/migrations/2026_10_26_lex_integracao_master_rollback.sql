-- Rollback de 2026_10_26_lex_integracao_master.sql
-- Master. Remove a configuração LEX. Os segredos deixam de existir neste banco.

DROP TABLE IF EXISTS `lex_escolas_config`;
