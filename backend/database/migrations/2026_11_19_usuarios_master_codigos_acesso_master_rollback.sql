-- Rollback da tabela de códigos de segundo fator do painel master.
-- Remove só essa tabela. Não altera usuarios_master.

DROP TABLE IF EXISTS usuarios_master_codigos_acesso;
