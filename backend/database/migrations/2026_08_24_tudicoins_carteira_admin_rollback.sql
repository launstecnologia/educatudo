-- Rollback: remove carteiras de admin e volta ENUM para aluno/professor/escola.
-- Apaga movimentações e saldos com user_type=admin antes de encolher o ENUM.

DELETE FROM `carteira_movimentacoes` WHERE `user_type` = 'admin';
DELETE FROM `carteira_usuarios` WHERE `user_type` = 'admin';

ALTER TABLE `carteira_movimentacoes`
  MODIFY COLUMN `user_type` ENUM('aluno','professor','escola') NOT NULL;

ALTER TABLE `carteira_usuarios`
  MODIFY COLUMN `user_type` ENUM('aluno','professor','escola') NOT NULL;
