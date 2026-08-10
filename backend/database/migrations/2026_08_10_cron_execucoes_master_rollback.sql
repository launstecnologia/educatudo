-- Rollback: remove histórico de execuções de cron no Master.

DROP TABLE IF EXISTS `cron_execucoes_escolas`;
DROP TABLE IF EXISTS `cron_execucoes`;
