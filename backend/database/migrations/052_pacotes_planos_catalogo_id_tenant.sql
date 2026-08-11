-- TENANT: rastrear linhas sincronizadas do catálogo Master (upsert por catalogo_*_id).

ALTER TABLE `pacotes_creditos`
  ADD COLUMN `catalogo_pacote_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK lógico ao master';

ALTER TABLE `pacotes_creditos`
  ADD UNIQUE KEY `uk_pacotes_catalogo_pacote_id` (`catalogo_pacote_id`);

ALTER TABLE `planos_creditos`
  ADD COLUMN `catalogo_plano_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'FK lógico ao master';

ALTER TABLE `planos_creditos`
  ADD UNIQUE KEY `uk_planos_catalogo_plano_id` (`catalogo_plano_id`);
