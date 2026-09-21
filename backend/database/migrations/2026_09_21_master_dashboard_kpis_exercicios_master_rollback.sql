-- Rollback MASTER: remove totais de exercícios do snapshot do dashboard.
-- Justificativa: desfaz a migration 2026_09_21_master_dashboard_kpis_exercicios_master.sql.

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'master_dashboard_kpis_escolas'
       AND COLUMN_NAME = 'total_exercicios') > 0,
    'ALTER TABLE `master_dashboard_kpis_escolas` DROP COLUMN `total_exercicios`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'master_dashboard_kpis_escolas'
       AND COLUMN_NAME = 'total_exercicios_ia') > 0,
    'ALTER TABLE `master_dashboard_kpis_escolas` DROP COLUMN `total_exercicios_ia`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'master_dashboard_kpis'
       AND COLUMN_NAME = 'total_exercicios') > 0,
    'ALTER TABLE `master_dashboard_kpis` DROP COLUMN `total_exercicios`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'master_dashboard_kpis'
       AND COLUMN_NAME = 'total_exercicios_ia') > 0,
    'ALTER TABLE `master_dashboard_kpis` DROP COLUMN `total_exercicios_ia`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
