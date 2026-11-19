-- Rollback master: remove EducaSync das tabelas de custo.

DELETE FROM `creditos_tabela_custo_item`
WHERE `modulo_key` IN ('educasync_otimizar', 'educasync_assistente_mensagem');
