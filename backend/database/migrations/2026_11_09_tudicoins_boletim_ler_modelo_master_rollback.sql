-- Rollback master: remove Boletim — ler modelo por IA das tabelas de custo.

DELETE FROM `creditos_tabela_custo_item`
WHERE `modulo_key` = 'boletim_ler_modelo';
