-- Contexto/Descrição da proposta (gerado por IA ou manual)
-- repertoire passa a armazenar JSON array de textos quando há múltiplos repertórios

ALTER TABLE `redacoes_orientadas_propostas`
  ADD COLUMN `contexto` TEXT NULL COMMENT 'Contexto/descrição da redação (pode ser gerado por IA)' AFTER `theme`;
