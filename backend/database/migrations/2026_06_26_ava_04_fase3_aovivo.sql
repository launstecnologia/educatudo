-- ============================================================================
-- AVA / EAD - Fase 3 (aulas ao vivo vinculadas a disciplina)
-- ----------------------------------------------------------------------------
-- ORDEM DE EXECUCAO (o runner ordena por nome de arquivo via strcmp):
--   1) 2026_06_26_ava_01_fase1.sql               <- estrutura base (tabelas AVA)
--   2) 2026_06_26_ava_02_professor_tutoria.sql   <- ALTER professores
--   3) 2026_06_26_ava_03_fase2.sql               <- atividades/rubricas/comentarios
--   4) 2026_06_26_ava_04_fase3_aovivo.sql        <- ESTE arquivo
--
-- NAO duplica o modulo de "Aulas Online" existente (tabela aulas_online, ligada
-- a turma). Esta tabela e propria do AVA: ligada a DISCIPLINA + professor, e
-- reaproveita os mesmos servicos (JaasMeetService / PandaVideoLiveService).
-- A sala Jitsi do AVA usa prefixo distinto (educatudo-avalive-) para a gravacao
-- nao colidir com o webhook de aulas_online.
--
-- Depende de ava_disciplinas e ava_modulos (Fase 1). Executar em cada tenant.
-- Idempotente (CREATE TABLE IF NOT EXISTS).
-- Rollback: 2026_06_26_ava_04_fase3_aovivo_rollback.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ava_aulas_ao_vivo` (
  `id`                         INT(11) NOT NULL AUTO_INCREMENT,
  `disciplina_id`              INT(11) NOT NULL,
  `modulo_id`                  INT(11) NULL,
  `professor_id`               INT(11) NULL,
  `titulo`                     VARCHAR(255) NOT NULL,
  `descricao`                  TEXT NULL,
  `plataforma`                 ENUM('jitsi','panda','externo') NOT NULL DEFAULT 'jitsi',
  `link_externo`               VARCHAR(500) NULL,
  `inicio_em`                  DATETIME NULL,
  `fim_em`                     DATETIME NULL,
  `status`                     ENUM('agendada','ao_vivo','encerrada','cancelada') NOT NULL DEFAULT 'agendada',
  `panda_live_id`              VARCHAR(120) NULL,
  `panda_live_player`          VARCHAR(500) NULL,
  `panda_recording_player`     VARCHAR(500) NULL,
  `panda_recording_hls`        VARCHAR(500) NULL,
  `panda_recording_synced_at`  DATETIME NULL,
  `gravacao_url`               VARCHAR(500) NULL,
  `created_at`                 DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                 DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_live_disc` (`disciplina_id`),
  KEY `idx_ava_live_modulo` (`modulo_id`),
  KEY `idx_ava_live_professor` (`professor_id`),
  KEY `idx_ava_live_inicio` (`inicio_em`),
  CONSTRAINT `fk_ava_live_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ava_live_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `ava_modulos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
