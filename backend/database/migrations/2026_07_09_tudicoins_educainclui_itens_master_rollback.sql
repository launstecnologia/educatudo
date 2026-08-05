-- Rollback master: remove itens EducaInclui das tabelas de custo.

DELETE FROM `creditos_tabela_custo_item`
WHERE `modulo_key` IN ('educainclui_analisar_laudo', 'educainclui_gerar_prova');
