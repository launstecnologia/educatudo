-- Migration incremental: estrutura de tipos de curso, cursos e vínculo com turmas

CREATE TABLE IF NOT EXISTS `tipos_curso` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(100) NOT NULL,
  `slug` varchar(100) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tipos_curso_nome` (`nome`),
  UNIQUE KEY `uq_tipos_curso_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cursos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_curso_id` int(11) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `slug` varchar(120) DEFAULT NULL,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cursos_tipo_nome` (`tipo_curso_id`,`nome`),
  UNIQUE KEY `uq_cursos_slug` (`slug`),
  KEY `idx_cursos_tipo` (`tipo_curso_id`),
  KEY `idx_cursos_ativo` (`ativo`),
  CONSTRAINT `fk_cursos_tipo_curso` FOREIGN KEY (`tipo_curso_id`) REFERENCES `tipos_curso` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'turmas'
    AND COLUMN_NAME = 'curso_id'
);
SET @sql := IF(
  @col_exists = 0,
  'ALTER TABLE `turmas` ADD COLUMN `curso_id` int(11) DEFAULT NULL AFTER `serie`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'turmas'
    AND CONSTRAINT_NAME = 'fk_turmas_curso'
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @col_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'turmas'
    AND COLUMN_NAME = 'curso_id'
);
SET @sql := IF(
  @fk_exists = 0 AND @col_exists > 0,
  'ALTER TABLE `turmas` ADD CONSTRAINT `fk_turmas_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`) ON DELETE SET NULL ON UPDATE CASCADE',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `tipos_curso` (`nome`, `slug`, `ordem`, `ativo`) VALUES
('Ensino Fundamental', 'fundamental', 10, 1),
('Ensino Médio', 'medio', 20, 1),
('Curso Superior', 'superior', 30, 1),
('Curso Preparatório', 'preparatorio', 40, 1);

INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '1º Ano', 'fundamental-1-ano', 10, 1 FROM tipos_curso tc WHERE tc.slug = 'fundamental';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '2º Ano', 'fundamental-2-ano', 20, 1 FROM tipos_curso tc WHERE tc.slug = 'fundamental';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '3º Ano', 'fundamental-3-ano', 30, 1 FROM tipos_curso tc WHERE tc.slug = 'fundamental';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '4º Ano', 'fundamental-4-ano', 40, 1 FROM tipos_curso tc WHERE tc.slug = 'fundamental';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '5º Ano', 'fundamental-5-ano', 50, 1 FROM tipos_curso tc WHERE tc.slug = 'fundamental';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '6º Ano', 'fundamental-6-ano', 60, 1 FROM tipos_curso tc WHERE tc.slug = 'fundamental';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '7º Ano', 'fundamental-7-ano', 70, 1 FROM tipos_curso tc WHERE tc.slug = 'fundamental';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '8º Ano', 'fundamental-8-ano', 80, 1 FROM tipos_curso tc WHERE tc.slug = 'fundamental';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '9º Ano', 'fundamental-9-ano', 90, 1 FROM tipos_curso tc WHERE tc.slug = 'fundamental';

INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '1º Ano', 'medio-1-ano', 10, 1 FROM tipos_curso tc WHERE tc.slug = 'medio';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '2º Ano', 'medio-2-ano', 20, 1 FROM tipos_curso tc WHERE tc.slug = 'medio';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '3º Ano', 'medio-3-ano', 30, 1 FROM tipos_curso tc WHERE tc.slug = 'medio';

INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '1º Período', 'superior-1-periodo', 10, 1 FROM tipos_curso tc WHERE tc.slug = 'superior';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '2º Período', 'superior-2-periodo', 20, 1 FROM tipos_curso tc WHERE tc.slug = 'superior';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '3º Período', 'superior-3-periodo', 30, 1 FROM tipos_curso tc WHERE tc.slug = 'superior';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '4º Período', 'superior-4-periodo', 40, 1 FROM tipos_curso tc WHERE tc.slug = 'superior';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '5º Período', 'superior-5-periodo', 50, 1 FROM tipos_curso tc WHERE tc.slug = 'superior';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '6º Período', 'superior-6-periodo', 60, 1 FROM tipos_curso tc WHERE tc.slug = 'superior';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '7º Período', 'superior-7-periodo', 70, 1 FROM tipos_curso tc WHERE tc.slug = 'superior';
INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, '8º Período', 'superior-8-periodo', 80, 1 FROM tipos_curso tc WHERE tc.slug = 'superior';

INSERT IGNORE INTO `cursos` (`tipo_curso_id`, `nome`, `slug`, `ordem`, `ativo`)
SELECT tc.id, 'Pré-vestibular', 'preparatorio-pre-vestibular', 10, 1 FROM tipos_curso tc WHERE tc.slug = 'preparatorio';

-- Backfill: sempre que houver coincidência única por nome, preencher curso_id sem alterar compatibilidade.
UPDATE `turmas` t
JOIN (
  SELECT c.nome, MIN(c.id) AS curso_id, COUNT(*) AS total
  FROM cursos c
  WHERE c.ativo = 1
  GROUP BY c.nome
) m ON m.nome = t.serie
SET t.curso_id = m.curso_id
WHERE t.curso_id IS NULL
  AND m.total = 1;
