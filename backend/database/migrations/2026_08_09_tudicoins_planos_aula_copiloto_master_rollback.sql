-- Rollback master: remove Meu Copiloto - Plano de Aula das tabelas de custo.

DELETE FROM `creditos_tabela_custo_item`
WHERE `modulo_key` = 'planos_aula_copiloto';
