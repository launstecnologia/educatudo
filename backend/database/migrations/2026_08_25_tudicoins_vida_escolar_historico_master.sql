-- Master: cadastra leitura de histórico escolar (OCR + IA) nas tabelas de custo ativas.
-- Idempotente. Rollback: 2026_08_25_tudicoins_vida_escolar_historico_master_rollback.sql

INSERT INTO `creditos_tabela_custo_item` (`tabela_id`, `modulo_key`, `creditos`, `cobra`, `nome_exibicao`)
SELECT t.id,
       'vida_escolar_ler_historico',
       1.0000,
       1,
       'Vida Escolar — ler histórico (OCR + IA)'
FROM `creditos_tabela_custo` t
WHERE t.ativo = 1
  AND NOT EXISTS (
    SELECT 1 FROM `creditos_tabela_custo_item` i
    WHERE i.tabela_id = t.id AND i.modulo_key = 'vida_escolar_ler_historico'
  );
