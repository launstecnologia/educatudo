-- MASTER apenas: precificação global (tabela modulos_preco_creditos).

ALTER TABLE `modulos_preco_creditos`
  MODIFY COLUMN `creditos` DECIMAL(12,4) UNSIGNED NOT NULL DEFAULT 1.0000
  COMMENT 'Custo em créditos por uso (permite decimais, ex.: 0,5)';
