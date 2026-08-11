-- Redação Livre: envios do professor (upload sem proposta/jornada) e correções
-- Permite corrigir redações sem criar jornada; aluno pode ser vinculado por nome no arquivo ou manualmente.

CREATE TABLE IF NOT EXISTS `redacao_livre_envios` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) unsigned NOT NULL,
  `student_name` varchar(255) DEFAULT NULL COMMENT 'Nome do aluno (pode vir do arquivo ou digitado)',
  `student_id` int(11) unsigned DEFAULT NULL COMMENT 'Aluno vinculado (opcional)',
  `turma_id` int(11) unsigned DEFAULT NULL COMMENT 'Sala/turma vinculada (opcional)',
  `original_filename` varchar(255) DEFAULT NULL,
  `content_image_path` varchar(512) DEFAULT NULL COMMENT 'Caminho ou key do arquivo (imagem/PDF)',
  `content_text` text DEFAULT NULL COMMENT 'Texto transcrito ou digitado',
  `ocr_text` text DEFAULT NULL COMMENT 'Texto extraído por OCR se houver',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `student_id` (`student_id`),
  KEY `turma_id` (`turma_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `redacao_livre_correcoes` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `envio_id` int(11) unsigned NOT NULL,
  `grades_json` text DEFAULT NULL COMMENT 'Notas por competência (IA)',
  `teacher_grades_json` text DEFAULT NULL COMMENT 'Notas do professor por competência',
  `feedback_text` text DEFAULT NULL,
  `suggestions_text` text DEFAULT NULL,
  `total_score` decimal(6,2) DEFAULT NULL,
  `ai_total_score` decimal(6,2) DEFAULT NULL,
  `teacher_total_score` decimal(6,2) DEFAULT NULL,
  `use_average` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = média entre IA e professor',
  `corrected_by_teacher_id` int(11) unsigned DEFAULT NULL,
  `teacher_adjusted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `envio_id` (`envio_id`),
  KEY `envio_id_2` (`envio_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
