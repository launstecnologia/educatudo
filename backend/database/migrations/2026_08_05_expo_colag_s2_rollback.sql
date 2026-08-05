-- Rollback Expo Colag S2
-- Justificativa: remove tabelas do wizard e autorização de imagem criadas no S2.

DROP TABLE IF EXISTS `expo_colag_alunos_autorizacao_imagem`;
DROP TABLE IF EXISTS `expo_colag_projeto_materiais`;
DROP TABLE IF EXISTS `expo_colag_projeto_rubrica`;
DROP TABLE IF EXISTS `expo_colag_projeto_encontros`;
DROP TABLE IF EXISTS `expo_colag_projeto_habilidades`;
DROP TABLE IF EXISTS `expo_colag_projeto_papeis`;
DROP TABLE IF EXISTS `expo_colag_projeto_tipos_trabalho`;
