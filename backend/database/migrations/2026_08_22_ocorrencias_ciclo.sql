-- Ocorrências do aluno: ciclo de vida, categoria por escola, snapshot de turma/ano
-- e vínculo opcional com o Diário. Estende alunos_ocorrencias (não cria registro paralelo).
-- Tenant. Idempotente. Rollback: 2026_08_22_ocorrencias_ciclo_rollback.sql
-- Não altera nota, frequência, alertas_sensiveis, reunioes nem documentos do aluno.

SET @db := DATABASE();

-- Categorias configuráveis por escola
CREATE TABLE IF NOT EXISTS `ocorrencias_categorias` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(40) NOT NULL,
  `nome` VARCHAR(80) NOT NULL,
  `ordem` SMALLINT NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ocorrencias_categorias_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ocorrencias_categorias` (`slug`, `nome`, `ordem`)
SELECT 'pedagogica', 'Pedagógica', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ocorrencias_categorias` WHERE `slug` = 'pedagogica');
INSERT INTO `ocorrencias_categorias` (`slug`, `nome`, `ordem`)
SELECT 'comportamento', 'Comportamento / convivência', 2 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ocorrencias_categorias` WHERE `slug` = 'comportamento');
INSERT INTO `ocorrencias_categorias` (`slug`, `nome`, `ordem`)
SELECT 'atraso', 'Atraso', 3 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ocorrencias_categorias` WHERE `slug` = 'atraso');
INSERT INTO `ocorrencias_categorias` (`slug`, `nome`, `ordem`)
SELECT 'saida_antecipada', 'Saída antecipada', 4 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ocorrencias_categorias` WHERE `slug` = 'saida_antecipada');
INSERT INTO `ocorrencias_categorias` (`slug`, `nome`, `ordem`)
SELECT 'material', 'Material', 5 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ocorrencias_categorias` WHERE `slug` = 'material');
INSERT INTO `ocorrencias_categorias` (`slug`, `nome`, `ordem`)
SELECT 'orientacao', 'Orientação', 6 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ocorrencias_categorias` WHERE `slug` = 'orientacao');
INSERT INTO `ocorrencias_categorias` (`slug`, `nome`, `ordem`)
SELECT 'elogio', 'Elogio', 7 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ocorrencias_categorias` WHERE `slug` = 'elogio');
INSERT INTO `ocorrencias_categorias` (`slug`, `nome`, `ordem`)
SELECT 'administrativa', 'Administrativa', 8 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ocorrencias_categorias` WHERE `slug` = 'administrativa');
INSERT INTO `ocorrencias_categorias` (`slug`, `nome`, `ordem`)
SELECT 'outra', 'Outra', 9 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `ocorrencias_categorias` WHERE `slug` = 'outra');

SET @has_oc := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias');

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='categoria_id');
SET @sql := IF(@has_oc>0 AND @col=0,
  "ALTER TABLE `alunos_ocorrencias` ADD COLUMN `categoria_id` INT NULL DEFAULT NULL AFTER `aluno_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- DEFAULT encerrada preenche só as linhas já existentes no ADD COLUMN.
-- Em seguida o default passa a aberta para inserts que não informarem o campo.
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='status');
SET @sql := IF(@has_oc>0 AND @col=0,
  "ALTER TABLE `alunos_ocorrencias` ADD COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'encerrada' AFTER `nivel_gravidade`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='status');
SET @sql := IF(@has_oc>0 AND @col>0,
  "ALTER TABLE `alunos_ocorrencias` MODIFY COLUMN `status` VARCHAR(30) NOT NULL DEFAULT 'aberta'",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='turma_id');
SET @sql := IF(@has_oc>0 AND @col=0,
  "ALTER TABLE `alunos_ocorrencias` ADD COLUMN `turma_id` INT NULL DEFAULT NULL AFTER `status`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='ano_letivo_id');
SET @sql := IF(@has_oc>0 AND @col=0,
  "ALTER TABLE `alunos_ocorrencias` ADD COLUMN `ano_letivo_id` INT NULL DEFAULT NULL AFTER `turma_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='diario_aula_id');
SET @sql := IF(@has_oc>0 AND @col=0,
  "ALTER TABLE `alunos_ocorrencias` ADD COLUMN `diario_aula_id` INT NULL DEFAULT NULL AFTER `ano_letivo_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='materia_id');
SET @sql := IF(@has_oc>0 AND @col=0,
  "ALTER TABLE `alunos_ocorrencias` ADD COLUMN `materia_id` INT NULL DEFAULT NULL AFTER `diario_aula_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='local');
SET @sql := IF(@has_oc>0 AND @col=0,
  "ALTER TABLE `alunos_ocorrencias` ADD COLUMN `local` VARCHAR(120) NULL DEFAULT NULL AFTER `materia_id`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='encaminhamento');
SET @sql := IF(@has_oc>0 AND @col=0,
  "ALTER TABLE `alunos_ocorrencias` ADD COLUMN `encaminhamento` TEXT NULL AFTER `local`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='responsavel_comunicado_em');
SET @sql := IF(@has_oc>0 AND @col=0,
  "ALTER TABLE `alunos_ocorrencias` ADD COLUMN `responsavel_comunicado_em` DATETIME NULL DEFAULT NULL AFTER `enviar_pais`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='encerrado_em');
SET @sql := IF(@has_oc>0 AND @col=0,
  "ALTER TABLE `alunos_ocorrencias` ADD COLUMN `encerrado_em` DATETIME NULL DEFAULT NULL AFTER `responsavel_comunicado_em`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND COLUMN_NAME='encerrado_por');
SET @sql := IF(@has_oc>0 AND @col=0,
  "ALTER TABLE `alunos_ocorrencias` ADD COLUMN `encerrado_por` INT NULL DEFAULT NULL AFTER `encerrado_em`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND INDEX_NAME='idx_ocorrencias_status');
SET @sql := IF(@has_oc>0 AND @idx=0,
  "ALTER TABLE `alunos_ocorrencias` ADD KEY `idx_ocorrencias_status` (`status`)",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND INDEX_NAME='idx_ocorrencias_categoria');
SET @sql := IF(@has_oc>0 AND @idx=0,
  "ALTER TABLE `alunos_ocorrencias` ADD KEY `idx_ocorrencias_categoria` (`categoria_id`)",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND INDEX_NAME='idx_ocorrencias_turma');
SET @sql := IF(@has_oc>0 AND @idx=0,
  "ALTER TABLE `alunos_ocorrencias` ADD KEY `idx_ocorrencias_turma` (`turma_id`)",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND INDEX_NAME='idx_ocorrencias_aula');
SET @sql := IF(@has_oc>0 AND @idx=0,
  "ALTER TABLE `alunos_ocorrencias` ADD KEY `idx_ocorrencias_aula` (`diario_aula_id`)",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_cat := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ocorrencias_categorias');
SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND CONSTRAINT_NAME='fk_ocorrencias_categoria' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@has_oc>0 AND @has_cat>0 AND @fk=0,
  "ALTER TABLE `alunos_ocorrencias` ADD CONSTRAINT `fk_ocorrencias_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `ocorrencias_categorias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_aulas := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_aulas');
SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='alunos_ocorrencias' AND CONSTRAINT_NAME='fk_ocorrencias_diario_aula' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@has_oc>0 AND @has_aulas>0 AND @fk=0,
  "ALTER TABLE `alunos_ocorrencias` ADD CONSTRAINT `fk_ocorrencias_diario_aula` FOREIGN KEY (`diario_aula_id`) REFERENCES `diario_aulas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `ocorrencias_historico` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `ocorrencia_id` INT NOT NULL,
  `usuario_id` INT NOT NULL,
  `acao` VARCHAR(40) NOT NULL,
  `motivo` VARCHAR(255) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ocorrencias_historico_ocorrencia` (`ocorrencia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_hist := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ocorrencias_historico');
SET @fk := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='ocorrencias_historico' AND CONSTRAINT_NAME='fk_ocorrencias_historico_ocorrencia' AND CONSTRAINT_TYPE='FOREIGN KEY');
SET @sql := IF(@has_hist>0 AND @has_oc>0 AND @fk=0,
  "ALTER TABLE `ocorrencias_historico` ADD CONSTRAINT `fk_ocorrencias_historico_ocorrencia` FOREIGN KEY (`ocorrencia_id`) REFERENCES `alunos_ocorrencias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
