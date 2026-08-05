-- Rollback: remove tabelas do módulo "IA da Apostila"
-- Ordem inversa de dependência (tabelas com apostila_id antes de apostilas_ia)
DROP TABLE IF EXISTS apostila_ia_conversas;
DROP TABLE IF EXISTS apostila_ia_exercicios;
DROP TABLE IF EXISTS apostila_ia_chunks;
DROP TABLE IF EXISTS apostila_ia_paginas;
DROP TABLE IF EXISTS apostilas_ia;
