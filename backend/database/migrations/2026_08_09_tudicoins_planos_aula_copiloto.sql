-- Tenant: habilita cobrança do Meu Copiloto em planos de aula quando TudiCoins estiver ativo.
-- Idempotente. Rollback: 2026_08_09_tudicoins_planos_aula_copiloto_rollback.sql

INSERT INTO `config_layout` (`config_key`, `config_value`)
VALUES
  ('credito_modulo_planos_aula_copiloto', '1'),
  ('credito_custo_planos_aula_copiloto', '1.0000')
ON DUPLICATE KEY UPDATE `config_value` = `config_value`;
