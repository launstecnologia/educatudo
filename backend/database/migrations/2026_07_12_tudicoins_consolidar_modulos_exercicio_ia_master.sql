-- Master: consolida gerar_questoes_prova + gerar_exercicios_jornada em
-- gerar_exercicio_ia_professor, e exercicios_ia_personalizado +
-- exercicio_ia_aluno em gerar_exercicio_ia_aluno — seeds nas tabelas de
-- custo ativas (idempotente), herdando cobra/creditos das chaves antigas
-- quando já configuradas. Não apaga as linhas antigas (ficam órfãs, mas
-- inofensivas: CreditosModuleRegistry::getModuleKeys()/getModuleLabels()
-- não iteram mais sobre chaves 'legado').

INSERT INTO `creditos_tabela_custo_item` (`tabela_id`, `modulo_key`, `creditos`, `cobra`, `nome_exibicao`)
SELECT t.id,
       'gerar_exercicio_ia_professor',
       COALESCE((
           SELECT MAX(i2.creditos) FROM `creditos_tabela_custo_item` i2
           WHERE i2.tabela_id = t.id AND i2.modulo_key IN ('gerar_questoes_prova', 'gerar_exercicios_jornada')
       ), 1.0000),
       COALESCE((
           SELECT MAX(i2.cobra) FROM `creditos_tabela_custo_item` i2
           WHERE i2.tabela_id = t.id AND i2.modulo_key IN ('gerar_questoes_prova', 'gerar_exercicios_jornada')
       ), 0),
       'Exercício/Questão via IA (Professor)'
FROM `creditos_tabela_custo` t
WHERE t.ativo = 1
  AND NOT EXISTS (
    SELECT 1 FROM `creditos_tabela_custo_item` i
    WHERE i.tabela_id = t.id AND i.modulo_key = 'gerar_exercicio_ia_professor'
  );

INSERT INTO `creditos_tabela_custo_item` (`tabela_id`, `modulo_key`, `creditos`, `cobra`, `nome_exibicao`)
SELECT t.id,
       'gerar_exercicio_ia_aluno',
       COALESCE((
           SELECT MAX(i2.creditos) FROM `creditos_tabela_custo_item` i2
           WHERE i2.tabela_id = t.id AND i2.modulo_key IN ('exercicios_ia_personalizado', 'exercicio_ia_aluno')
       ), 1.0000),
       COALESCE((
           SELECT MAX(i2.cobra) FROM `creditos_tabela_custo_item` i2
           WHERE i2.tabela_id = t.id AND i2.modulo_key IN ('exercicios_ia_personalizado', 'exercicio_ia_aluno')
       ), 0),
       'Exercício via IA (Aluno)'
FROM `creditos_tabela_custo` t
WHERE t.ativo = 1
  AND NOT EXISTS (
    SELECT 1 FROM `creditos_tabela_custo_item` i
    WHERE i.tabela_id = t.id AND i.modulo_key = 'gerar_exercicio_ia_aluno'
  );
