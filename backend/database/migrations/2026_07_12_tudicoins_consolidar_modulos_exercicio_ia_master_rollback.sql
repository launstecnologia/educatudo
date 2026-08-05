-- Rollback master: remove os itens consolidados das tabelas de custo.
-- As chaves legado (gerar_questoes_prova, gerar_exercicios_jornada,
-- exercicios_ia_personalizado, exercicio_ia_aluno) nunca foram apagadas
-- pela migration original, não precisam de rollback aqui.

DELETE FROM `creditos_tabela_custo_item`
WHERE `modulo_key` IN ('gerar_exercicio_ia_professor', 'gerar_exercicio_ia_aluno');
