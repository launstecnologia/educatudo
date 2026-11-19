-- Master: cadastra EducaSync (otimizar grade e assistente) nas tabelas de custo ativas.
-- Idempotente. Rollback: 2026_11_12_tudicoins_educasync_master_rollback.sql

INSERT INTO `creditos_tabela_custo_item` (`tabela_id`, `modulo_key`, `creditos`, `cobra`, `nome_exibicao`)
SELECT t.id,
       'educasync_otimizar',
       1.0000,
       1,
       'EducaSync — otimizar grade horária'
FROM `creditos_tabela_custo` t
WHERE t.ativo = 1
  AND NOT EXISTS (
    SELECT 1 FROM `creditos_tabela_custo_item` i
    WHERE i.tabela_id = t.id AND i.modulo_key = 'educasync_otimizar'
  );

INSERT INTO `creditos_tabela_custo_item` (`tabela_id`, `modulo_key`, `creditos`, `cobra`, `nome_exibicao`)
SELECT t.id,
       'educasync_assistente_mensagem',
       0.2000,
       1,
       'EducaSync — mensagem ao assistente'
FROM `creditos_tabela_custo` t
WHERE t.ativo = 1
  AND NOT EXISTS (
    SELECT 1 FROM `creditos_tabela_custo_item` i
    WHERE i.tabela_id = t.id AND i.modulo_key = 'educasync_assistente_mensagem'
  );
