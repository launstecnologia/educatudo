-- Master: cadastra Boletim — ler modelo por IA nas tabelas de custo ativas.
-- Idempotente. Rollback: 2026_11_09_tudicoins_boletim_ler_modelo_master_rollback.sql

INSERT INTO `creditos_tabela_custo_item` (`tabela_id`, `modulo_key`, `creditos`, `cobra`, `nome_exibicao`)
SELECT t.id,
       'boletim_ler_modelo',
       1.0000,
       1,
       'Boletim — ler modelo por IA'
FROM `creditos_tabela_custo` t
WHERE t.ativo = 1
  AND NOT EXISTS (
    SELECT 1 FROM `creditos_tabela_custo_item` i
    WHERE i.tabela_id = t.id AND i.modulo_key = 'boletim_ler_modelo'
  );
