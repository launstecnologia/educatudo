-- Rollback: remove propostas de escrita do Assistente Admin.
-- Seguro: só apaga a tabela criada pela migration correspondente.

DROP TABLE IF EXISTS `assistente_propostas`;
