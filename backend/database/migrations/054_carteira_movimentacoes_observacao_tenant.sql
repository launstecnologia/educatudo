-- TENANT (banco de cada escola). Detalhe do consumo (ex.: app externo Tudinha 2.0) e referências mais longas.
-- Não executar no banco master.

ALTER TABLE `carteira_movimentacoes`
  ADD COLUMN `observacao` VARCHAR(512) NULL DEFAULT NULL COMMENT 'Detalhe amigável do consumo (ex.: descrição enviada pelo app)' AFTER `referencia_id`,
  MODIFY COLUMN `referencia_id` VARCHAR(128) NULL DEFAULT NULL;
