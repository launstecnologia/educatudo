-- Rollback de 2026_10_04_aluno_pdfs_emitidos.sql
-- Remove o índice dos PDFs emitidos. Os arquivos no storage da escola permanecem.

DROP TABLE IF EXISTS `aluno_pdfs_emitidos`;
