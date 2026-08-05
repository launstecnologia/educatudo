-- ============================================================================
-- AVA / EAD - Avaliacoes da disciplina (vinculo com prova existente)
-- ----------------------------------------------------------------------------
-- ORDEM DE EXECUCAO (o runner ordena por nome de arquivo via strcmp):
--   ... ava_05_fase3_certificados.sql
--   2026_06_26_ava_06_avaliacoes.sql   <- ESTE arquivo
--
-- Vincula uma PROVA existente (modulo Provas: tabela `provas`) a uma disciplina
-- do AVA. A prova so e liberada para o aluno quando seu progresso na disciplina
-- atinge `requisito_progresso_pct`. A nota volta da `provas_realizacoes`.
--
-- `prova_id` usa coluna indexada SEM FK rigida (padrao multi-tenant: a tabela
-- `provas` pertence ao ERP). FK apenas para `ava_disciplinas` (mesmo modulo).
--
-- Executar em cada banco de tenant. Idempotente (CREATE TABLE IF NOT EXISTS).
-- Rollback: 2026_06_26_ava_06_avaliacoes_rollback.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ava_disciplina_avaliacoes` (
  `id`                       INT(11) NOT NULL AUTO_INCREMENT,
  `disciplina_id`            INT(11) NOT NULL,
  `prova_id`                 INT(11) NOT NULL,
  `titulo`                   VARCHAR(255) NULL,
  `requisito_progresso_pct`  DECIMAL(5,2) NOT NULL DEFAULT 80.00,
  `obrigatoria`              TINYINT(1) NOT NULL DEFAULT 1,
  `peso`                     DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `ordem`                    INT(11) NOT NULL DEFAULT 0,
  `created_at`               DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ava_disc_aval` (`disciplina_id`, `prova_id`),
  KEY `idx_ava_disc_aval_disc` (`disciplina_id`),
  KEY `idx_ava_disc_aval_prova` (`prova_id`),
  CONSTRAINT `fk_ava_disc_aval_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
