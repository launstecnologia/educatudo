-- MASTER apenas. Totais de exercícios feitos (IA e consolidado) no snapshot do dashboard.

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'master_dashboard_kpis'
       AND COLUMN_NAME = 'total_exercicios_ia') = 0,
    'ALTER TABLE `master_dashboard_kpis`
        ADD COLUMN `total_exercicios_ia` BIGINT UNSIGNED NOT NULL DEFAULT 0
            COMMENT ''Questões de exercícios por IA respondidas pelos alunos'' AFTER `total_provas`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'master_dashboard_kpis'
       AND COLUMN_NAME = 'total_exercicios') = 0,
    'ALTER TABLE `master_dashboard_kpis`
        ADD COLUMN `total_exercicios` BIGINT UNSIGNED NOT NULL DEFAULT 0
            COMMENT ''Exercícios feitos: jornada + prova + IA + unidade'' AFTER `total_exercicios_ia`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'master_dashboard_kpis_escolas'
       AND COLUMN_NAME = 'total_exercicios_ia') = 0,
    'ALTER TABLE `master_dashboard_kpis_escolas`
        ADD COLUMN `total_exercicios_ia` BIGINT UNSIGNED NOT NULL DEFAULT 0
            COMMENT ''Questões de exercícios por IA respondidas pelos alunos'' AFTER `total_provas`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'master_dashboard_kpis_escolas'
       AND COLUMN_NAME = 'total_exercicios') = 0,
    'ALTER TABLE `master_dashboard_kpis_escolas`
        ADD COLUMN `total_exercicios` BIGINT UNSIGNED NOT NULL DEFAULT 0
            COMMENT ''Exercícios feitos: jornada + prova + IA + unidade'' AFTER `total_exercicios_ia`',
    'SELECT 1'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
