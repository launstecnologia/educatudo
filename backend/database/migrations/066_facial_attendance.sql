-- Controle simples de entrada/saída por reconhecimento facial.
-- O descritor biométrico e a imagem não são armazenados no EducaTudo.

CREATE TABLE IF NOT EXISTS `student_face_profiles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `external_key` varchar(120) NOT NULL,
  `consent_at` datetime NOT NULL,
  `consent_by_user_id` int DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_face_profile_student` (`student_id`),
  UNIQUE KEY `uq_face_profile_external_key` (`external_key`),
  CONSTRAINT `fk_face_profile_student` FOREIGN KEY (`student_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_face_samples` (
  `id` int NOT NULL AUTO_INCREMENT,
  `face_profile_id` int NOT NULL,
  `provider_face_id` varchar(100) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_face_sample_provider` (`provider_face_id`),
  KEY `idx_face_sample_profile` (`face_profile_id`),
  CONSTRAINT `fk_face_sample_profile` FOREIGN KEY (`face_profile_id`) REFERENCES `student_face_profiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_access_events` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `student_id` int NOT NULL,
  `kind` enum('entrada','saida') NOT NULL,
  `event_at` datetime NOT NULL,
  `confidence` decimal(6,5) DEFAULT NULL,
  `provider_presence_id` varchar(100) NOT NULL,
  `registered_by_user_id` int DEFAULT NULL,
  `notified_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_access_event_provider` (`provider_presence_id`),
  KEY `idx_access_event_student_date` (`student_id`, `event_at`),
  KEY `idx_access_event_date` (`event_at`),
  CONSTRAINT `fk_access_event_student` FOREIGN KEY (`student_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
