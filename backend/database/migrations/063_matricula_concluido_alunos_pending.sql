-- Garante valores usados pelo fluxo de matrícula/secretaria em bancos criados antes da M5 ou sem PENDING.

ALTER TABLE `matricula`
  MODIFY COLUMN `status` ENUM('ativa','transferido','concluido') NOT NULL DEFAULT 'ativa';

ALTER TABLE `alunos`
  MODIFY COLUMN `status` ENUM('ACTIVE','INACTIVE','GRADUATED','SUSPENDED','PENDING') NOT NULL DEFAULT 'ACTIVE';
