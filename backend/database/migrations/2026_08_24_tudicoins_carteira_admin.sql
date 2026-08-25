-- Tenant: carteira de TudiCoins para usuário admin (cota mensal B2B).
-- user_type=admin / user_id = usuarios.id (tipo admin ou admin_escola).

ALTER TABLE `carteira_usuarios`
  MODIFY COLUMN `user_type` ENUM('aluno','professor','escola','admin') NOT NULL;

ALTER TABLE `carteira_movimentacoes`
  MODIFY COLUMN `user_type` ENUM('aluno','professor','escola','admin') NOT NULL;
