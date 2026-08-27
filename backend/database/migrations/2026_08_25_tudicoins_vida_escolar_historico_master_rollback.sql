-- Rollback master: remove leitura de histórico escolar das tabelas de custo.

DELETE FROM `creditos_tabela_custo_item`
WHERE `modulo_key` = 'vida_escolar_ler_historico';
