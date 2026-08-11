-- ============================================================================
-- AVA / EAD - Fase 2 (atividades/tarefas com entrega, rubricas e comentarios)
-- ----------------------------------------------------------------------------
-- ORDEM DE EXECUCAO (o runner ordena por nome de arquivo via strcmp):
--   1) 2026_06_26_ava_01_fase1.sql               <- estrutura base (tabelas AVA)
--   2) 2026_06_26_ava_02_professor_tutoria.sql   <- ALTER professores
--   3) 2026_06_26_ava_03_fase2.sql               <- ESTE arquivo
--
-- Avaliacoes/quiz NAO entram aqui: o EducaTudo ja possui o modulo de Provas
-- Online (tabelas provas/provas_questoes/...). Forum tambem ja existe. Esta
-- fase cobre apenas o que NAO existe: entrega de tarefas (arquivo/texto/link)
-- com correcao por rubrica e comentarios/duvidas por aula.
--
-- Depende das tabelas criadas na Fase 1 (ava_disciplinas, ava_modulos,
-- ava_aulas). Todas as FKs apontam para tabelas AVA ja existentes ou criadas
-- ANTES neste mesmo arquivo. Referencias ao ERP (alunos, professores) usam
-- coluna indexada SEM FK rigida (padrao multi-tenant do EducaTudo).
--
-- Executar em cada banco de tenant. Idempotente (CREATE TABLE IF NOT EXISTS).
-- Rollback: 2026_06_26_ava_03_fase2_rollback.sql
-- ============================================================================

-- ---- Rubricas de avaliacao -------------------------------------------------
CREATE TABLE IF NOT EXISTS `ava_rubricas` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `disciplina_id` INT(11) NULL,
  `titulo`        VARCHAR(255) NOT NULL,
  `descricao`     TEXT NULL,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_rubricas_disc` (`disciplina_id`),
  CONSTRAINT `fk_ava_rubricas_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ava_rubrica_criterios` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `rubrica_id`    INT(11) NOT NULL,
  `titulo`        VARCHAR(255) NOT NULL,
  `descricao`     TEXT NULL,
  `peso`          DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `pontuacao_max` DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  `ordem`         INT(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_ava_rub_crit_rubrica` (`rubrica_id`),
  CONSTRAINT `fk_ava_rub_crit_rubrica` FOREIGN KEY (`rubrica_id`) REFERENCES `ava_rubricas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Atividades / Tarefas com entrega --------------------------------------
CREATE TABLE IF NOT EXISTS `ava_atividades` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `disciplina_id`  INT(11) NOT NULL,
  `modulo_id`      INT(11) NULL,
  `aula_id`        INT(11) NULL,
  `professor_id`   INT(11) NULL,
  `rubrica_id`     INT(11) NULL,
  `titulo`         VARCHAR(255) NOT NULL,
  `descricao`      TEXT NULL,
  `instrucoes`     MEDIUMTEXT NULL,
  `tipo_entrega`   ENUM('arquivo','texto','link','multiplo') NOT NULL DEFAULT 'arquivo',
  `peso`           DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `nota_maxima`    DECIMAL(5,2) NOT NULL DEFAULT 10.00,
  `data_abertura`  DATETIME NULL,
  `data_entrega`   DATETIME NULL,
  `aceita_atraso`  TINYINT(1) NOT NULL DEFAULT 0,
  `permite_reenvio` TINYINT(1) NOT NULL DEFAULT 1,
  `max_arquivos`   INT(11) NOT NULL DEFAULT 5,
  `tamanho_max_mb` INT(11) NOT NULL DEFAULT 20,
  `status`         ENUM('rascunho','publicada','encerrada') NOT NULL DEFAULT 'publicada',
  `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_atv_disc` (`disciplina_id`),
  KEY `idx_ava_atv_modulo` (`modulo_id`),
  KEY `idx_ava_atv_aula` (`aula_id`),
  KEY `idx_ava_atv_professor` (`professor_id`),
  KEY `idx_ava_atv_rubrica` (`rubrica_id`),
  CONSTRAINT `fk_ava_atv_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ava_atv_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `ava_modulos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ava_atv_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ava_atv_rubrica` FOREIGN KEY (`rubrica_id`) REFERENCES `ava_rubricas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ava_atividade_entregas` (
  `id`                    INT(11) NOT NULL AUTO_INCREMENT,
  `atividade_id`          INT(11) NOT NULL,
  `aluno_id`              INT(11) NOT NULL,
  `texto`                 MEDIUMTEXT NULL,
  `link`                  VARCHAR(500) NULL,
  `status`                ENUM('rascunho','enviada','avaliada','reenviar') NOT NULL DEFAULT 'enviada',
  `nota`                  DECIMAL(5,2) NULL,
  `feedback`              MEDIUMTEXT NULL,
  `rubrica_resultado_json` JSON NULL,
  `atrasada`              TINYINT(1) NOT NULL DEFAULT 0,
  `enviada_em`            DATETIME NULL,
  `avaliada_em`           DATETIME NULL,
  `avaliada_por`          INT(11) NULL,
  `created_at`            DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ava_entrega` (`atividade_id`, `aluno_id`),
  KEY `idx_ava_entrega_aluno` (`aluno_id`),
  CONSTRAINT `fk_ava_entrega_atividade` FOREIGN KEY (`atividade_id`) REFERENCES `ava_atividades` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ava_atividade_entrega_arquivos` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `entrega_id`  INT(11) NOT NULL,
  `arquivo_key` VARCHAR(500) NOT NULL,
  `nome`        VARCHAR(255) NULL,
  `mime`        VARCHAR(120) NULL,
  `tamanho`     INT(11) NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_entrega_arq_entrega` (`entrega_id`),
  CONSTRAINT `fk_ava_entrega_arq_entrega` FOREIGN KEY (`entrega_id`) REFERENCES `ava_atividade_entregas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---- Comentarios / Duvidas por aula ----------------------------------------
CREATE TABLE IF NOT EXISTS `ava_comentarios` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `aula_id`    INT(11) NOT NULL,
  `parent_id`  INT(11) NULL,
  `autor_tipo` ENUM('aluno','professor') NOT NULL DEFAULT 'aluno',
  `autor_id`   INT(11) NOT NULL,
  `autor_nome` VARCHAR(255) NULL,
  `conteudo`   TEXT NOT NULL,
  `fixado`     TINYINT(1) NOT NULL DEFAULT 0,
  `removido`   TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_coment_aula` (`aula_id`),
  KEY `idx_ava_coment_parent` (`parent_id`),
  CONSTRAINT `fk_ava_coment_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ava_coment_parent` FOREIGN KEY (`parent_id`) REFERENCES `ava_comentarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
