-- ============================================================================
-- AVA / EAD - Fase 1 (estrutura de curriculo + matricula + progresso)
-- ----------------------------------------------------------------------------
-- ORDEM DE EXECUCAO (o runner ordena por nome de arquivo via strcmp):
--   1) 2026_06_26_ava_01_fase1.sql               <- ESTE arquivo (tabelas)
--   2) 2026_06_26_ava_02_professor_tutoria.sql   <- ALTER professores
--
-- Todas as FKs apontam apenas para tabelas AVA criadas ANTES neste mesmo
-- arquivo, garantindo execucao livre de problemas de ordem. Referencias a
-- tabelas do ERP (alunos, professores, materias, turmas) usam coluna indexada
-- SEM FK rigida (padrao multi-tenant do EducaTudo).
--
-- Executar em cada banco de tenant. Idempotente (CREATE TABLE IF NOT EXISTS).
-- Rollback: 2026_06_26_ava_01_fase1_rollback.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ava_categorias` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `nome`       VARCHAR(150) NOT NULL,
  `slug`       VARCHAR(160) NULL,
  `descricao`  VARCHAR(500) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_categorias_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ava_cursos` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `nome`          VARCHAR(255) NOT NULL,
  `codigo`        VARCHAR(60) NULL,
  `modalidade`    ENUM('fundamental','medio','tecnico','graduacao','pos','livre') NOT NULL DEFAULT 'livre',
  `categoria_id`  INT(11) NULL,
  `carga_horaria` INT(11) NOT NULL DEFAULT 0,
  `descricao`     TEXT NULL,
  `objetivos`     TEXT NULL,
  `competencias`  TEXT NULL,
  `bibliografia`  TEXT NULL,
  `certificacao`  TINYINT(1) NOT NULL DEFAULT 0,
  `imagem_key`    VARCHAR(500) NULL,
  `banner_key`    VARCHAR(500) NULL,
  `status`        ENUM('rascunho','ativo','arquivado') NOT NULL DEFAULT 'rascunho',
  `created_by`    INT(11) NULL,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_cursos_categoria` (`categoria_id`),
  KEY `idx_ava_cursos_status` (`status`),
  CONSTRAINT `fk_ava_cursos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `ava_categorias` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ava_semestres` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `curso_id`    INT(11) NOT NULL,
  `nome`        VARCHAR(150) NOT NULL,
  `ordem`       INT(11) NOT NULL DEFAULT 0,
  `data_inicio` DATE NULL,
  `data_fim`    DATE NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_semestres_curso` (`curso_id`),
  CONSTRAINT `fk_ava_semestres_curso` FOREIGN KEY (`curso_id`) REFERENCES `ava_cursos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ava_disciplinas` (
  `id`                 INT(11) NOT NULL AUTO_INCREMENT,
  `curso_id`           INT(11) NOT NULL,
  `semestre_id`        INT(11) NULL,
  `nome`               VARCHAR(255) NOT NULL,
  `codigo`             VARCHAR(60) NULL,
  `professor_id`       INT(11) NULL,
  `tutor_id`           INT(11) NULL,
  `ordem`              INT(11) NOT NULL DEFAULT 0,
  `carga_horaria`      INT(11) NOT NULL DEFAULT 0,
  `horas_ead`          INT(11) NOT NULL DEFAULT 0,
  `horas_presenciais`  INT(11) NOT NULL DEFAULT 0,
  `ementa`             TEXT NULL,
  `objetivos`          TEXT NULL,
  `competencias`       TEXT NULL,
  `materia_id`         INT(11) NULL,
  `turma_id`           INT(11) NULL,
  `status`             ENUM('rascunho','ativo','arquivado') NOT NULL DEFAULT 'ativo',
  `created_at`         DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_disc_curso` (`curso_id`),
  KEY `idx_ava_disc_semestre` (`semestre_id`),
  KEY `idx_ava_disc_professor` (`professor_id`),
  KEY `idx_ava_disc_tutor` (`tutor_id`),
  KEY `idx_ava_disc_materia` (`materia_id`),
  KEY `idx_ava_disc_turma` (`turma_id`),
  CONSTRAINT `fk_ava_disc_curso` FOREIGN KEY (`curso_id`) REFERENCES `ava_cursos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ava_disc_semestre` FOREIGN KEY (`semestre_id`) REFERENCES `ava_semestres` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ava_modulos` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `disciplina_id` INT(11) NOT NULL,
  `titulo`        VARCHAR(255) NOT NULL,
  `descricao`     TEXT NULL,
  `ordem`         INT(11) NOT NULL DEFAULT 0,
  `status`        ENUM('rascunho','publicado') NOT NULL DEFAULT 'publicado',
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_modulos_disc` (`disciplina_id`),
  CONSTRAINT `fk_ava_modulos_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ava_aulas` (
  `id`                  INT(11) NOT NULL AUTO_INCREMENT,
  `modulo_id`           INT(11) NOT NULL,
  `titulo`              VARCHAR(255) NOT NULL,
  `descricao`           TEXT NULL,
  `professor_id`        INT(11) NULL,
  `tipo`                ENUM('video','texto','pdf','apresentacao','audio','link','html','quiz') NOT NULL DEFAULT 'video',
  `conteudo_html`       MEDIUMTEXT NULL,
  `video_provider`      ENUM('none','mp4','youtube','vimeo','bunny','cloudflare','panda') NOT NULL DEFAULT 'none',
  `video_ref`           VARCHAR(500) NULL,
  `duracao_seg`         INT(11) NOT NULL DEFAULT 0,
  `imagem_key`          VARCHAR(500) NULL,
  `tempo_estimado_min`  INT(11) NOT NULL DEFAULT 0,
  `data_liberacao`      DATETIME NULL,
  `data_encerramento`   DATETIME NULL,
  `obrigatoria`         TINYINT(1) NOT NULL DEFAULT 1,
  `permite_download`    TINYINT(1) NOT NULL DEFAULT 0,
  `permite_comentarios` TINYINT(1) NOT NULL DEFAULT 1,
  `ordem`               INT(11) NOT NULL DEFAULT 0,
  `created_at`          DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_aulas_modulo` (`modulo_id`),
  KEY `idx_ava_aulas_professor` (`professor_id`),
  CONSTRAINT `fk_ava_aulas_modulo` FOREIGN KEY (`modulo_id`) REFERENCES `ava_modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ava_aula_anexos` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `aula_id`     INT(11) NOT NULL,
  `tipo`        VARCHAR(40) NOT NULL DEFAULT 'arquivo',
  `arquivo_key` VARCHAR(500) NULL,
  `nome`        VARCHAR(255) NULL,
  `mime`        VARCHAR(120) NULL,
  `tamanho`     INT(11) NULL,
  `url`         VARCHAR(500) NULL,
  `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ava_anexos_aula` (`aula_id`),
  CONSTRAINT `fk_ava_anexos_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ava_matriculas_disciplina` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `aluno_id`       INT(11) NOT NULL,
  `disciplina_id`  INT(11) NOT NULL,
  `origem`         ENUM('erp','manual') NOT NULL DEFAULT 'manual',
  `status`         ENUM('ativa','concluida','trancada','cancelada') NOT NULL DEFAULT 'ativa',
  `progresso_pct`  DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `nota_final`     DECIMAL(5,2) NULL,
  `data_matricula` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `concluida_em`   DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ava_matricula` (`aluno_id`, `disciplina_id`),
  KEY `idx_ava_matricula_disc` (`disciplina_id`),
  KEY `idx_ava_matricula_aluno` (`aluno_id`),
  CONSTRAINT `fk_ava_matricula_disc` FOREIGN KEY (`disciplina_id`) REFERENCES `ava_disciplinas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ava_progresso_aula` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `aluno_id`     INT(11) NOT NULL,
  `aula_id`      INT(11) NOT NULL,
  `status`       ENUM('nao_iniciada','em_andamento','concluida') NOT NULL DEFAULT 'nao_iniciada',
  `percentual`   DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `concluida_em` DATETIME NULL,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ava_prog_aula` (`aluno_id`, `aula_id`),
  KEY `idx_ava_prog_aula_aula` (`aula_id`),
  CONSTRAINT `fk_ava_prog_aula_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ava_progresso_video` (
  `id`                  INT(11) NOT NULL AUTO_INCREMENT,
  `aluno_id`            INT(11) NOT NULL,
  `aula_id`             INT(11) NOT NULL,
  `segundo_atual`       INT(11) NOT NULL DEFAULT 0,
  `tempo_assistido_seg` INT(11) NOT NULL DEFAULT 0,
  `duracao_seg`         INT(11) NOT NULL DEFAULT 0,
  `percentual`          DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `concluido`           TINYINT(1) NOT NULL DEFAULT 0,
  `ultimo_acesso`       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ava_prog_video` (`aluno_id`, `aula_id`),
  KEY `idx_ava_prog_video_aula` (`aula_id`),
  CONSTRAINT `fk_ava_prog_video_aula` FOREIGN KEY (`aula_id`) REFERENCES `ava_aulas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
