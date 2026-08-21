-- Rollback: 2026_08_21_provas_log_eventos.sql

SET @db := DATABASE();

SET @has_tabela := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='provas_log_eventos');
SET @sql := IF(@has_tabela>0, "DROP TABLE `provas_log_eventos`", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
