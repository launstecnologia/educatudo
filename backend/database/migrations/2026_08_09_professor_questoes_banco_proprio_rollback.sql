-- Rollback de 2026_08_09_professor_questoes_banco_proprio.sql.
-- Remove metadados do banco próprio do professor.

SET @db := DATABASE();
SET @has_table := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api'
);

SET @idx := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND INDEX_NAME = 'idx_prof_questoes_nivel'
);
SET @sql := IF(@has_table > 0 AND @idx > 0, "ALTER TABLE professor_questoes_api DROP INDEX idx_prof_questoes_nivel", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND INDEX_NAME = 'idx_prof_questoes_professor'
);
SET @sql := IF(@has_table > 0 AND @idx > 0, "ALTER TABLE professor_questoes_api DROP INDEX idx_prof_questoes_professor", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND COLUMN_NAME = 'origem_referencia'
);
SET @sql := IF(@has_table > 0 AND @col > 0, "ALTER TABLE professor_questoes_api DROP COLUMN origem_referencia", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND COLUMN_NAME = 'origem'
);
SET @sql := IF(@has_table > 0 AND @col > 0, "ALTER TABLE professor_questoes_api DROP COLUMN origem", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND COLUMN_NAME = 'nivel_dificuldade'
);
SET @sql := IF(@has_table > 0 AND @col > 0, "ALTER TABLE professor_questoes_api DROP COLUMN nivel_dificuldade", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND COLUMN_NAME = 'assunto'
);
SET @sql := IF(@has_table > 0 AND @col > 0, "ALTER TABLE professor_questoes_api DROP COLUMN assunto", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND COLUMN_NAME = 'titulo'
);
SET @sql := IF(@has_table > 0 AND @col > 0, "ALTER TABLE professor_questoes_api DROP COLUMN titulo", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND COLUMN_NAME = 'professor_id'
);
SET @sql := IF(@has_table > 0 AND @col > 0, "ALTER TABLE professor_questoes_api DROP COLUMN professor_id", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
