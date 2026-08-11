-- Cache da explicação Tudinha por aluno + exercício de módulo (evita texto diferente a cada abertura do modal).
-- Reexecutável: só cria se a tabela ainda não existir.

CREATE TABLE IF NOT EXISTS `jornadas_tudinha_explicacao_exercicio` (
  `id` int NOT NULL AUTO_INCREMENT,
  `aluno_id` int NOT NULL,
  `exercicio_modulo_id` int NOT NULL,
  `fonte_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 dos dados da questão e da resposta do aluno',
  `explicacao_html` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_aluno_exercicio_modulo` (`aluno_id`,`exercicio_modulo_id`),
  KEY `idx_jtee_exercicio` (`exercicio_modulo_id`),
  CONSTRAINT `jtee_fk_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jtee_fk_exercicio` FOREIGN KEY (`exercicio_modulo_id`) REFERENCES `jornadas_modulos_exercicios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
