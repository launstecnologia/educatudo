-- Rollback Expo Colag S1
-- Justificativa: remove tabelas criadas na migration de preparação do módulo (ainda sem dados de produção esperados neste estágio).

DROP TABLE IF EXISTS `expo_colag_inscricoes`;
DROP TABLE IF EXISTS `expo_colag_projeto_etapas`;
DROP TABLE IF EXISTS `expo_colag_projeto_visibilidade`;
DROP TABLE IF EXISTS `expo_colag_projeto_objetivos`;
DROP TABLE IF EXISTS `expo_colag_projeto_professores`;
DROP TABLE IF EXISTS `expo_colag_projeto_materias`;
DROP TABLE IF EXISTS `expo_colag_projetos`;
DROP TABLE IF EXISTS `expo_colag_edicoes`;
