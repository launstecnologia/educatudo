-- Rollback intencionalmente no-op: esta migration corrige um dado corrompido
-- (status desatualizado por um bug de reenvio, já corrigido em código), não
-- uma alteração de schema. Não existe um "status anterior correto" para
-- restaurar — o valor anterior ('submitted') era o próprio bug. Reverter
-- voltaria a exibir como pendente/enviada uma redação que de fato já tem
-- correção salva.
SELECT 1;
