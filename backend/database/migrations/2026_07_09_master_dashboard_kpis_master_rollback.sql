-- Rollback: remove tabelas de KPIs do dashboard Master.

DROP TABLE IF EXISTS `master_dashboard_kpis_escolas`;
DROP TABLE IF EXISTS `master_dashboard_kpis`;
