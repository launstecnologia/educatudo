-- Diário de Classe (Fase 1 da reestruturação — ver specs/PRD.md §9):
-- 1) Tabela de fechamento por período (Turma + Componente Curricular +
--    Professor + Ano Letivo + Bimestre), mesmo padrão simples de
--    provas_blocos.ano_letivo/bimestre (sem catálogo à parte).
-- 2) Amplia diario_frequencias.situacao com 'saida_antecipada'.
-- Tenant. Idempotente. Rollback: 2026_08_19_diario_fechamento_rollback.sql

SET @db := DATABASE();

-- Tabela diario_fechamentos
SET @has_fechamentos := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_fechamentos');
SET @sql := IF(@has_fechamentos=0,
  "CREATE TABLE `diario_fechamentos` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `turma_id` INT NOT NULL,
    `materia_id` INT NOT NULL,
    `professor_id` INT NOT NULL,
    `ano_letivo` SMALLINT UNSIGNED NOT NULL,
    `bimestre` TINYINT UNSIGNED NOT NULL COMMENT 'Bimestre 1 a 4',
    `status` ENUM('aberto','fechado') NOT NULL DEFAULT 'aberto',
    `fechado_por` INT DEFAULT NULL,
    `fechado_em` DATETIME DEFAULT NULL,
    `reaberto_por` INT DEFAULT NULL,
    `reaberto_em` DATETIME DEFAULT NULL,
    `observacoes` TEXT DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_diario_fechamento` (`turma_id`, `materia_id`, `professor_id`, `ano_letivo`, `bimestre`),
    KEY `idx_diario_fechamento_turma` (`turma_id`),
    KEY `idx_diario_fechamento_professor` (`professor_id`),
    KEY `idx_diario_fechamento_status` (`status`),
    CONSTRAINT `fk_diario_fechamento_turma` FOREIGN KEY (`turma_id`) REFERENCES `turmas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_diario_fechamento_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_diario_fechamento_professor` FOREIGN KEY (`professor_id`) REFERENCES `professores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- diario_frequencias.situacao: adiciona 'saida_antecipada' ao ENUM existente
-- (só altera se a tabela existir e o ENUM ainda não tiver o valor novo).
SET @has_frequencias := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_frequencias');
SET @enum_atual := (SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_frequencias' AND COLUMN_NAME='situacao');
SET @sql := IF(@has_frequencias>0 AND @enum_atual IS NOT NULL AND @enum_atual NOT LIKE '%saida_antecipada%',
  "ALTER TABLE `diario_frequencias` MODIFY COLUMN `situacao` ENUM('presente','falta','falta_justificada','atraso','saida_antecipada') NOT NULL DEFAULT 'presente'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
