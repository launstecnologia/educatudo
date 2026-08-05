-- Tenant: compras_creditos aceita user_type=escola (compra institucional TudiCoins).

ALTER TABLE `compras_creditos`
  MODIFY COLUMN `user_type` ENUM('aluno','professor','escola') NOT NULL;
