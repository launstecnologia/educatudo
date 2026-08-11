-- Histórico de mudanças de status do aluno (inativação / TR / LGPD)

CREATE TABLE IF NOT EXISTS `alunos_historico_status` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `old_status` varchar(20) NOT NULL,
  `new_status` varchar(20) NOT NULL,
  `reason` varchar(30) NOT NULL,
  `observation` text NOT NULL,
  `changed_by` int NOT NULL,
  `ip` varchar(45) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_student_status_student` (`student_id`),
  KEY `idx_student_status_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Se a coluna status ainda não existir em alunos, rodar também:
-- ALTER TABLE `alunos`
--   ADD COLUMN `status` ENUM('ACTIVE','INACTIVE','GRADUATED','SUSPENDED','PENDING') NOT NULL DEFAULT 'ACTIVE' AFTER `ativo`;
--
-- Se a coluna já existir sem PENDING, rodar:
-- ALTER TABLE `alunos`
--   MODIFY COLUMN `status` ENUM('ACTIVE','INACTIVE','GRADUATED','SUSPENDED','PENDING') NOT NULL DEFAULT 'ACTIVE';
