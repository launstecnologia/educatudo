-- Manter jornadas sempre como avaliativas.
-- Execute no MySQL do tenant.

UPDATE jornadas
SET avaliativo = 1
WHERE avaliativo <> 1 OR avaliativo IS NULL;

ALTER TABLE jornadas
  MODIFY COLUMN avaliativo TINYINT(1) NOT NULL DEFAULT 1
  COMMENT 'Sempre 1 (sim)';
