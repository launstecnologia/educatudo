-- Master: seeds das chaves EducaInclui nas tabelas de custo ativas (idempotente).

INSERT INTO `creditos_tabela_custo_item` (`tabela_id`, `modulo_key`, `creditos`, `cobra`, `nome_exibicao`)
SELECT t.id, 'educainclui_analisar_laudo', 1.0000, 1, 'EducaInclui — analisar laudo (OCR + IA)'
FROM `creditos_tabela_custo` t
WHERE t.ativo = 1
  AND NOT EXISTS (
    SELECT 1 FROM `creditos_tabela_custo_item` i
    WHERE i.tabela_id = t.id AND i.modulo_key = 'educainclui_analisar_laudo'
  );

INSERT INTO `creditos_tabela_custo_item` (`tabela_id`, `modulo_key`, `creditos`, `cobra`, `nome_exibicao`)
SELECT t.id, 'educainclui_gerar_prova', 1.0000, 1, 'EducaInclui — gerar prova adaptada'
FROM `creditos_tabela_custo` t
WHERE t.ativo = 1
  AND NOT EXISTS (
    SELECT 1 FROM `creditos_tabela_custo_item` i
    WHERE i.tabela_id = t.id AND i.modulo_key = 'educainclui_gerar_prova'
  );
