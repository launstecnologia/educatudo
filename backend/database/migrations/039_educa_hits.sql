-- Migration: Módulo EducaHits - Músicas educativas
-- Data: 2026-03-17

CREATE TABLE IF NOT EXISTS `educa_hits_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL COMMENT 'ID do aluno (alunos.id)',
  `school_id` int(11) DEFAULT NULL COMMENT 'ID da escola (multi-tenant)',
  `class_id` int(11) DEFAULT NULL COMMENT 'ID da turma (turmas.id)',
  `grade` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Série do aluno',
  `subject` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Matéria',
  `topic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Tema',
  `music_style` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Estilo musical',
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Descrição da música solicitada',
  `status` enum('pending','in_progress','completed','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_educa_hits_requests_user` (`user_id`),
  KEY `idx_educa_hits_requests_status` (`status`),
  KEY `idx_educa_hits_requests_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `educa_hits_tracks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_id` int(11) DEFAULT NULL COMMENT 'Pedido que originou (opcional)',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audio_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Caminho do áudio',
  `cover_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Capa da música',
  `lyrics` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Letra da música',
  `duration` int(11) DEFAULT NULL COMMENT 'Duração em segundos',
  `created_by_admin` int(11) DEFAULT NULL,
  `status` enum('draft','published','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_educa_hits_tracks_request` (`request_id`),
  KEY `idx_educa_hits_tracks_status` (`status`),
  KEY `idx_educa_hits_tracks_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `educa_hits_track_schools` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `track_id` int(11) NOT NULL,
  `school_id` int(11) DEFAULT NULL COMMENT 'NULL = todas as escolas (single-tenant)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_track_school` (`track_id`,`school_id`),
  KEY `idx_educa_hits_ts_school` (`school_id`),
  CONSTRAINT `fk_educa_hits_ts_track` FOREIGN KEY (`track_id`) REFERENCES `educa_hits_tracks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `educa_hits_track_grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `track_id` int(11) NOT NULL,
  `grade` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Série (ex: 1º ano, 2º ano)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_track_grade` (`track_id`,`grade`),
  KEY `idx_educa_hits_tg_grade` (`grade`),
  CONSTRAINT `fk_educa_hits_tg_track` FOREIGN KEY (`track_id`) REFERENCES `educa_hits_tracks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `educa_hits_track_classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `track_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL COMMENT 'turmas.id',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_track_class` (`track_id`,`class_id`),
  KEY `idx_educa_hits_tc_class` (`class_id`),
  CONSTRAINT `fk_educa_hits_tc_track` FOREIGN KEY (`track_id`) REFERENCES `educa_hits_tracks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_educa_hits_tc_class` FOREIGN KEY (`class_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro do módulo em config_layout (single-tenant usa config_layout)
-- Valor 0 = desabilitado por padrão; 1 = habilitado
INSERT INTO config_layout (config_key, config_value) 
VALUES ('module_educa_hits', '0')
ON DUPLICATE KEY UPDATE config_key = config_key;
