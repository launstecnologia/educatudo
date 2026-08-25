-- Matriz Curricular: configuração acadêmica que define o que cada série de um
-- curso deve cursar (Componentes Curriculares vinculados, aulas/semana,
-- obrigatoriedade). Referencia curso/serie reais (tabelas 023_curso.sql e
-- 024_serie.sql, já usadas por turmas.curso_novo_id/serie_id — migrations
-- 028/031). Turma ganha vínculo opcional com uma matriz.
-- Tenant. Idempotente. Rollback: 2026_08_18_matriz_curricular_rollback.sql

SET @db := DATABASE();

-- Tabela matrizes_curriculares
SET @has_matrizes := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matrizes_curriculares');
SET @sql := IF(@has_matrizes=0,
  "CREATE TABLE `matrizes_curriculares` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nome` VARCHAR(150) NOT NULL,
    `codigo` VARCHAR(30) NOT NULL,
    `curso_id` INT NOT NULL,
    `serie_id` INT NOT NULL,
    `modalidade` VARCHAR(50) DEFAULT NULL,
    `turno` VARCHAR(20) DEFAULT NULL,
    `carga_horaria_anual_prevista` DECIMAL(6,2) DEFAULT NULL,
    `dias_letivos_previstos` INT DEFAULT NULL,
    `duracao_padrao_aula_minutos` INT NOT NULL DEFAULT 50,
    `base_legal` VARCHAR(255) DEFAULT NULL,
    `observacoes` TEXT DEFAULT NULL,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_matrizes_codigo` (`codigo`),
    KEY `idx_matrizes_curso_serie` (`curso_id`, `serie_id`),
    KEY `idx_matrizes_ativo` (`ativo`),
    CONSTRAINT `fk_matrizes_curso` FOREIGN KEY (`curso_id`) REFERENCES `curso` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_matrizes_serie` FOREIGN KEY (`serie_id`) REFERENCES `serie` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Tabela matrizes_curriculares_componentes
SET @has_mcc := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matrizes_curriculares_componentes');
SET @sql := IF(@has_mcc=0,
  "CREATE TABLE `matrizes_curriculares_componentes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `matriz_id` INT NOT NULL,
    `materia_id` INT NOT NULL,
    `aulas_semana` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `obrigatorio` TINYINT(1) NOT NULL DEFAULT 1,
    `ordem_boletim` INT NOT NULL DEFAULT 0,
    `ordem_historico` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_matriz_materia` (`matriz_id`, `materia_id`),
    KEY `fk_mcc_materia` (`materia_id`),
    CONSTRAINT `fk_mcc_matriz` FOREIGN KEY (`matriz_id`) REFERENCES `matrizes_curriculares` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_mcc_materia` FOREIGN KEY (`materia_id`) REFERENCES `materias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- turmas.matriz_curricular_id (nullable — só usado pelo fluxo "nova estrutura",
-- ver ClassController::store/update)
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND COLUMN_NAME='matriz_curricular_id');
SET @sql := IF(@col=0,
  "ALTER TABLE `turmas` ADD COLUMN `matriz_curricular_id` INT DEFAULT NULL AFTER `serie_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='turmas' AND CONSTRAINT_NAME='fk_turmas_matriz_curricular' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @has_matrizes_for_fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='matrizes_curriculares');
SET @sql := IF(@fk=0 AND @has_matrizes_for_fk>0,
  "ALTER TABLE `turmas` ADD CONSTRAINT `fk_turmas_matriz_curricular` FOREIGN KEY (`matriz_curricular_id`) REFERENCES `matrizes_curriculares` (`id`) ON DELETE SET NULL ON UPDATE CASCADE",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
