-- Rollback: remove carteira da escola e volta ENUM para aluno/professor.
-- Pré-requisito: não pode haver movimentações com user_type=escola (apaga-as).

DELETE FROM `carteira_movimentacoes` WHERE `user_type` = 'escola';
DELETE FROM `carteira_usuarios` WHERE `user_type` = 'escola';

ALTER TABLE `carteira_movimentacoes`
  MODIFY COLUMN `user_type` ENUM('aluno','professor') NOT NULL;

ALTER TABLE `carteira_usuarios`
  MODIFY COLUMN `user_type` ENUM('aluno','professor') NOT NULL;
