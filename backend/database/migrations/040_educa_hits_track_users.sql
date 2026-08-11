-- Migration: EducaHits - Entrega para aluno específico
-- Permite entregar música apenas para um ou mais alunos individuais

CREATE TABLE IF NOT EXISTS `educa_hits_track_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `track_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'alunos.id',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_track_user` (`track_id`,`user_id`),
  KEY `idx_educa_hits_tu_user` (`user_id`),
  CONSTRAINT `fk_educa_hits_tu_track` FOREIGN KEY (`track_id`) REFERENCES `educa_hits_tracks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
