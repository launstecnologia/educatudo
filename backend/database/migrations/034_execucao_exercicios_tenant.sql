-- Tabela execucao_exercicios (nome usada pelo AdminController / gestão de exercícios).
-- Alguns tenants têm apenas exercicios_execucoes; esta migration garante que
-- execucao_exercicios exista para evitar "Base table or view not found".
-- Execute no banco do TENANT (não no master).

CREATE TABLE IF NOT EXISTS `execucao_exercicios` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `aluno_id` INT NOT NULL,
  `lista_id` INT NOT NULL,
  `data_inicio` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `data_fim` TIMESTAMP NULL DEFAULT NULL,
  `tempo_total` INT DEFAULT 0,
  `questoes_corretas` INT DEFAULT 0,
  `questoes_total` INT DEFAULT 0,
  `percentual_acerto` DECIMAL(5,2) DEFAULT 0.00,
  `status` ENUM('em_andamento','finalizado','pausado') DEFAULT 'em_andamento',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_execucao_aluno` (`aluno_id`),
  KEY `idx_execucao_lista` (`lista_id`),
  KEY `idx_execucao_lista_status` (`lista_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Execução de lista de exercícios por aluno (AdminController)';
