-- Rollback do seed 2026_07_15_historico_escolar_seed_demo.sql
-- Remove apenas o documento demo (hash fixo). Não reverte dados de unidade/aluno.

DELETE FROM historico_assinaturas
WHERE historico_id IN (
  SELECT id FROM historico_documentos
  WHERE hash_validacao = 'demodemo0123456789abcdef0123456789abcdef0123456789abcdef01234567'
);

DELETE FROM historico_resultados_anuais
WHERE historico_id IN (
  SELECT id FROM historico_documentos
  WHERE hash_validacao = 'demodemo0123456789abcdef0123456789abcdef0123456789abcdef01234567'
);

DELETE FROM historico_itens
WHERE historico_id IN (
  SELECT id FROM historico_documentos
  WHERE hash_validacao = 'demodemo0123456789abcdef0123456789abcdef0123456789abcdef01234567'
);

DELETE FROM historico_documentos
WHERE hash_validacao = 'demodemo0123456789abcdef0123456789abcdef0123456789abcdef01234567';
