-- Rollback tenant: remove configuração de cobrança do Meu Copiloto em planos de aula.

DELETE FROM `config_layout`
WHERE `config_key` IN (
  'credito_modulo_planos_aula_copiloto',
  'credito_custo_planos_aula_copiloto'
);
