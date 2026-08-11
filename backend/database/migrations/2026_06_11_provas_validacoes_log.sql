-- Histórico de validações de nota (tentativas canceladas validadas pelo coordenador).
-- Também é criada automaticamente em runtime (Exam::garantirTabelaValidacoesLog).
CREATE TABLE IF NOT EXISTS `provas_validacoes_log` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `prova_id` INT NOT NULL,
  `aluno_id` INT NOT NULL,
  `bloco_id` INT NULL,
  `nota` DECIMAL(10,2) NULL,
  `validado_por_id` INT NULL,
  `validado_por_nome` VARCHAR(255) NULL,
  `validado_por_tipo` VARCHAR(30) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prova_aluno` (`prova_id`, `aluno_id`),
  KEY `idx_bloco` (`bloco_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
