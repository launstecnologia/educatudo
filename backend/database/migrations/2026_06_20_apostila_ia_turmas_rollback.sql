-- Rollback: remove tabela de ligação apostila_ia <-> turmas (N:N)
DROP TABLE IF EXISTS apostila_ia_turmas;
