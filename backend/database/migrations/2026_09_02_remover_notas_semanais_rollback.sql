-- Recria as tabelas do quadro semanal legado (migration 2026_08_14_notas_semanais_quadro.sql).

CREATE TABLE IF NOT EXISTS `notas_semanais_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `semanas_grupo_a` VARCHAR(32) NOT NULL DEFAULT '1,3,5,7',
  `semanas_grupo_b` VARCHAR(32) NOT NULL DEFAULT '2,4,6,8',
  `peso_media_sem` DECIMAL(5,2) NOT NULL DEFAULT 4.00,
  `peso_prova_bim` DECIMAL(5,2) NOT NULL DEFAULT 4.00,
  `peso_enac` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `peso_participacao` DECIMAL(5,2) NOT NULL DEFAULT 0.50,
  `peso_trabalho` DECIMAL(5,2) NOT NULL DEFAULT 0.50,
  `regra_recuperacao` VARCHAR(32) NOT NULL DEFAULT 'maior',
  `media_minima` DECIMAL(4,2) NOT NULL DEFAULT 6.00,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO notas_semanais_config (id) VALUES (1);

CREATE TABLE IF NOT EXISTS `notas_semanais_materias` (
  `materia_id` INT NOT NULL,
  `grupo` CHAR(1) NOT NULL COMMENT 'A ou B',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`materia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
