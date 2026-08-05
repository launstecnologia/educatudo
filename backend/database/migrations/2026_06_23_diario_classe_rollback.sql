-- Rollback do Diário de Classe (2026_06_23_diario_classe.sql)
-- Remove primeiro a tabela filha (FK) e depois a tabela pai.
DROP TABLE IF EXISTS diario_frequencias;
DROP TABLE IF EXISTS diario_aulas;
