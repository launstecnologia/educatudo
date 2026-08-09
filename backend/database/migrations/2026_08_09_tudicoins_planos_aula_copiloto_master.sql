-- Master: cadastra Meu Copiloto - Plano de Aula nas tabelas de custo ativas.
-- Idempotente. Rollback: 2026_08_09_tudicoins_planos_aula_copiloto_master_rollback.sql

INSERT INTO `creditos_tabela_custo_item` (`tabela_id`, `modulo_key`, `creditos`, `cobra`, `nome_exibicao`)
SELECT t.id,
       'planos_aula_copiloto',
       1.0000,
       1,
       'Meu Copiloto - Plano de Aula'
FROM `creditos_tabela_custo` t
WHERE t.ativo = 1
  AND NOT EXISTS (
    SELECT 1 FROM `creditos_tabela_custo_item` i
    WHERE i.tabela_id = t.id AND i.modulo_key = 'planos_aula_copiloto'
  );
