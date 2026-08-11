-- Enriquece professor_questoes_api para também servir como banco próprio do professor.
-- Tenant. Idempotente. Rollback: 2026_08_09_professor_questoes_banco_proprio_rollback.sql

SET @db := DATABASE();
SET @has_table := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api'
);

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND COLUMN_NAME = 'professor_id'
);
SET @sql := IF(
    @has_table > 0 AND @col = 0,
    "ALTER TABLE professor_questoes_api
       ADD COLUMN `professor_id` BIGINT UNSIGNED NULL AFTER `id`",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND COLUMN_NAME = 'titulo'
);
SET @sql := IF(
    @has_table > 0 AND @col = 0,
    "ALTER TABLE professor_questoes_api
       ADD COLUMN `titulo` VARCHAR(180) NULL AFTER `external_id`",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND COLUMN_NAME = 'assunto'
);
SET @sql := IF(
    @has_table > 0 AND @col = 0,
    "ALTER TABLE professor_questoes_api
       ADD COLUMN `assunto` VARCHAR(180) NULL AFTER `materia`",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND COLUMN_NAME = 'nivel_dificuldade'
);
SET @sql := IF(
    @has_table > 0 AND @col = 0,
    "ALTER TABLE professor_questoes_api
       ADD COLUMN `nivel_dificuldade` VARCHAR(20) NULL AFTER `tipo`",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND COLUMN_NAME = 'origem'
);
SET @sql := IF(
    @has_table > 0 AND @col = 0,
    "ALTER TABLE professor_questoes_api
       ADD COLUMN `origem` VARCHAR(40) NOT NULL DEFAULT 'api_externa' AFTER `nivel_dificuldade`",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND COLUMN_NAME = 'origem_referencia'
);
SET @sql := IF(
    @has_table > 0 AND @col = 0,
    "ALTER TABLE professor_questoes_api
       ADD COLUMN `origem_referencia` VARCHAR(120) NULL AFTER `origem`",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND INDEX_NAME = 'idx_prof_questoes_professor'
);
SET @sql := IF(
    @has_table > 0 AND @idx = 0,
    "ALTER TABLE professor_questoes_api ADD KEY idx_prof_questoes_professor (professor_id, updated_at)",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professor_questoes_api' AND INDEX_NAME = 'idx_prof_questoes_nivel'
);
SET @sql := IF(
    @has_table > 0 AND @idx = 0,
    "ALTER TABLE professor_questoes_api ADD KEY idx_prof_questoes_nivel (nivel_dificuldade)",
    'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
