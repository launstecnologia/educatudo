-- ============================================================================
-- AVA / EAD - Fase 3 (certificados de conclusao)
-- ----------------------------------------------------------------------------
-- ORDEM DE EXECUCAO (o runner ordena por nome de arquivo via strcmp):
--   1) 2026_06_26_ava_01_fase1.sql               <- estrutura base
--   2) 2026_06_26_ava_02_professor_tutoria.sql   <- ALTER professores
--   3) 2026_06_26_ava_03_fase2.sql               <- atividades/rubricas/comentarios
--   4) 2026_06_26_ava_04_fase3_aovivo.sql        <- aulas ao vivo
--   5) 2026_06_26_ava_05_fase3_certificados.sql  <- ESTE arquivo
--
-- Registra os certificados emitidos para validacao publica por codigo. O PDF e
-- gerado sob demanda (dompdf), nao e armazenado. Conclusao e aferida em
-- ava_matriculas_disciplina (status='concluida') + flag ava_cursos.certificacao.
--
-- Depende de ava_disciplinas (Fase 1). Executar em cada tenant.
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- Rollback: 2026_06_26_ava_05_fase3_certificados_rollback.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ava_certificados` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `aluno_id`      INT(11) NOT NULL,
  `disciplina_id` INT(11) NULL,
  `curso_id`      INT(11) NULL,
  `tipo`          ENUM('disciplina','curso') NOT NULL DEFAULT 'disciplina',
  `codigo`        VARCHAR(40) NOT NULL,
  `aluno_nome`    VARCHAR(255) NULL,
  `titulo`        VARCHAR(255) NULL,
  `carga_horaria` INT(11) NOT NULL DEFAULT 0,
  `nota_final`    DECIMAL(5,2) NULL,
  `emitido_em`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ava_cert_codigo` (`codigo`),
  UNIQUE KEY `uk_ava_cert_aluno_disc` (`aluno_id`, `disciplina_id`),
  KEY `idx_ava_cert_aluno` (`aluno_id`),
  KEY `idx_ava_cert_disc` (`disciplina_id`),
  CONSTRAINT `fk_ava_cert_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
