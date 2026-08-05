-- Telefone e celular do aluno

ALTER TABLE `alunos`
  ADD COLUMN `telefone` VARCHAR(20) NULL DEFAULT NULL AFTER `email`,
  ADD COLUMN `celular` VARCHAR(20) NULL DEFAULT NULL AFTER `telefone`;
