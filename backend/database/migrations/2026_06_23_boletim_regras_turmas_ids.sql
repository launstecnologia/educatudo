-- Permite limitar um evento de Notas/Boletim a turmas específicas.
-- A aplicação também garante esta coluna automaticamente em ensureSchema().

ALTER TABLE boletim_regras
  ADD COLUMN turmas_ids TEXT NULL AFTER series_ids;
