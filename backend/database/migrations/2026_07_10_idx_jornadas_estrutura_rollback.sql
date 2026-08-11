-- Rollback: 2026_07_10_idx_jornadas_estrutura
ALTER TABLE jornadas
    DROP INDEX IF EXISTS idx_estrutura;
