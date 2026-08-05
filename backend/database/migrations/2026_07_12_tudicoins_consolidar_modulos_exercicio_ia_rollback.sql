-- Rollback tenant: remove as duas chaves consolidadas de config_layout.
-- As chaves antigas (credito_modulo_gerar_questoes_prova etc.) nunca foram
-- apagadas pela migration original, não precisam de rollback aqui.

DELETE FROM `config_layout`
WHERE `config_key` IN ('credito_modulo_gerar_exercicio_ia_professor', 'credito_modulo_gerar_exercicio_ia_aluno');
