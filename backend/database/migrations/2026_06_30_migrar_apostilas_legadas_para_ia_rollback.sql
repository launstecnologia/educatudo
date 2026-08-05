-- Reverte migração de apostilas legadas: remove registros migrados e a coluna de rastreio.
DELETE FROM apostila_ia_turmas
WHERE apostila_id IN (SELECT id FROM apostilas_ia WHERE legado_modulo_id IS NOT NULL);

DELETE FROM apostilas_ia WHERE legado_modulo_id IS NOT NULL;

ALTER TABLE apostilas_ia DROP COLUMN IF EXISTS legado_modulo_id;
