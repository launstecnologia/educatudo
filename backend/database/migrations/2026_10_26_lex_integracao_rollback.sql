-- Rollback de 2026_10_26_lex_integracao.sql
-- Tenant. Remove o histórico e os vínculos LEX. Não apaga alunos, professores, responsáveis nem usuários.

DROP TABLE IF EXISTS `lex_sincronizacao_eventos`;
DROP TABLE IF EXISTS `lex_sincronizacoes`;
DROP TABLE IF EXISTS `lex_vinculos`;
