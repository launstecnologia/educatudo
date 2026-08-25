-- Rollback do Conselho de Classe. Tenant.
-- DROP das tabelas na ordem inversa das FKs.

SET @db := DATABASE();

DROP TABLE IF EXISTS `conselho_observacoes`;
DROP TABLE IF EXISTS `conselho_atas`;
DROP TABLE IF EXISTS `conselho_encaminhamentos`;
DROP TABLE IF EXISTS `conselho_deliberacoes`;
DROP TABLE IF EXISTS `conselho_participantes`;
DROP TABLE IF EXISTS `conselho_sessoes`;
