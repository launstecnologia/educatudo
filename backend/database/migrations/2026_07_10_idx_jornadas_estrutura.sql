-- Migration: 2026_07_10_idx_jornadas_estrutura
-- Adiciona índice na coluna estrutura da tabela jornadas para evitar full scan
-- nas queries que buscam jornadas por estrutura (cron, aluno, api pais)

SELECT COUNT(*) INTO @existe FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'jornadas' AND INDEX_NAME = 'idx_estrutura';
SET @sql = IF(@existe = 0, 'ALTER TABLE jornadas ADD INDEX idx_estrutura (estrutura(100))', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
