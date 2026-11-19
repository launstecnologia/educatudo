-- Remove as tabelas do EducaSync (cenários, versões, alocações, execuções, publicações).
-- Tenant. O DROP apaga os rascunhos e o histórico de publicação de propósito.
-- A grade_horaria já publicada NÃO é tocada.

DROP TABLE IF EXISTS `educasync_publicacoes`;
DROP TABLE IF EXISTS `educasync_comandos_ia`;
DROP TABLE IF EXISTS `educasync_execucoes`;
DROP TABLE IF EXISTS `educasync_alocacoes`;
DROP TABLE IF EXISTS `educasync_versoes`;
DROP TABLE IF EXISTS `educasync_restricoes`;
DROP TABLE IF EXISTS `educasync_disponibilidades`;
DROP TABLE IF EXISTS `educasync_demandas`;
DROP TABLE IF EXISTS `educasync_slots`;
DROP TABLE IF EXISTS `educasync_cenarios`;
