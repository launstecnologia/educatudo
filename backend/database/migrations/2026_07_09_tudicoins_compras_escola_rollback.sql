-- Rollback: remove compras escola e volta ENUM.

DELETE FROM `compras_creditos` WHERE `user_type` = 'escola';

ALTER TABLE `compras_creditos`
  MODIFY COLUMN `user_type` ENUM('aluno','professor') NOT NULL;
