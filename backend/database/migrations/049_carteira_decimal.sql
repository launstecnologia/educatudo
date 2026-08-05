-- TENANT (banco de cada escola): saldo e movimentações com decimais.
-- Não contém "master" no nome → executado nas escolas, não no banco administrador.

ALTER TABLE `carteira_usuarios`
  MODIFY COLUMN `saldo` DECIMAL(14,4) NOT NULL DEFAULT 0.0000;
ALTER TABLE `carteira_movimentacoes`
  MODIFY COLUMN `valor` DECIMAL(14,4) NOT NULL COMMENT 'Positivo=entrada, negativo=consumo';
