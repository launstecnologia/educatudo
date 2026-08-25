-- Gestão de Presença: log de entrada/saída (catraca, secretaria, facial),
-- identificadores de crachá, conectores e origem da marca no diário/boletim.
-- Não recria faltas_lancamentos: o consolidado do boletim continua nessa tabela
-- e passa a ser recalculado (não incrementado) a partir do diário.
-- Tenant. Idempotente. Rollback: 2026_08_22_gestao_presenca_rollback.sql

SET @db := DATABASE();

-- Configuração da escola (uma linha)
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='presenca_config');
SET @sql := IF(@has=0,
  "CREATE TABLE `presenca_config` (
    `id` TINYINT UNSIGNED NOT NULL,
    `tolerancia_atraso_min` SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    `minutos_corte_sem_entrada` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    `criar_aula_rascunho` TINYINT(1) NOT NULL DEFAULT 1,
    `consolidar_boletim` TINYINT(1) NOT NULL DEFAULT 0,
    `data_corte` DATE DEFAULT NULL COMMENT 'Diário só alimenta boletim a partir desta data',
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `presenca_config` (`id`) VALUES (1);

-- Conector por fornecedor (webhook ou polling). Segredo só como hash.
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='presenca_integracoes');
SET @sql := IF(@has=0,
  "CREATE TABLE `presenca_integracoes` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `nome` VARCHAR(120) NOT NULL,
    `provedor` VARCHAR(60) NOT NULL DEFAULT 'generico',
    `modo` ENUM('webhook','polling') NOT NULL DEFAULT 'webhook',
    `mapeamento_identificador` ENUM('ra','codigo_aluno','aluno_id','cartao') NOT NULL DEFAULT 'ra',
    `token_hash` CHAR(64) DEFAULT NULL,
    `ativo` TINYINT(1) NOT NULL DEFAULT 1,
    `ultimo_erro` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_presenca_int_ativo` (`ativo`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Crachá / cartão da catraca → aluno
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='presenca_identificadores');
SET @sql := IF(@has=0,
  "CREATE TABLE `presenca_identificadores` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `aluno_id` INT NOT NULL,
    `tipo` ENUM('cartao','ra','codigo_aluno','externo') NOT NULL DEFAULT 'cartao',
    `valor` VARCHAR(80) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_presenca_ident_tipo_valor` (`tipo`, `valor`),
    KEY `idx_presenca_ident_aluno` (`aluno_id`),
    CONSTRAINT `fk_presenca_ident_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Fato de entrada/saída (append-only). Idempotência por id_externo prefixado.
SET @has := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='presenca_eventos');
SET @sql := IF(@has=0,
  "CREATE TABLE `presenca_eventos` (
    `id` BIGINT NOT NULL AUTO_INCREMENT,
    `aluno_id` INT DEFAULT NULL,
    `tipo` ENUM('entrada','saida') NOT NULL,
    `ocorrido_em` DATETIME NOT NULL,
    `origem` ENUM('integracao','manual_secretaria','facial','importacao') NOT NULL DEFAULT 'integracao',
    `integracao_id` INT DEFAULT NULL,
    `id_externo` VARCHAR(190) NOT NULL,
    `identificador_bruto` VARCHAR(80) DEFAULT NULL,
    `registrado_por` INT DEFAULT NULL,
    `processado_em` DATETIME DEFAULT NULL,
    `erro_processamento` VARCHAR(255) DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_presenca_id_externo` (`id_externo`),
    KEY `idx_presenca_aluno_quando` (`aluno_id`, `ocorrido_em`),
    KEY `idx_presenca_quando` (`ocorrido_em`),
    KEY `idx_presenca_origem` (`origem`),
    CONSTRAINT `fk_presenca_evt_aluno` FOREIGN KEY (`aluno_id`) REFERENCES `alunos` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_presenca_evt_integracao` FOREIGN KEY (`integracao_id`) REFERENCES `presenca_integracoes` (`id`) ON DELETE SET NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Origem da situação na chamada (professor vs catraca vs secretaria)
SET @has_freq := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_frequencias');
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='diario_frequencias' AND COLUMN_NAME='origem');
SET @sql := IF(@has_freq>0 AND @col=0,
  "ALTER TABLE `diario_frequencias` ADD COLUMN `origem` ENUM('manual_diario','integracao','entrada_saida','ajuste_gestao','importacao') NOT NULL DEFAULT 'manual_diario' AFTER `observacao`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Evento de faltas do boletim: manual (digitado) ou alimentado pelo diário
SET @has_ev := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=@db AND TABLE_NAME='faltas_eventos');
SET @col := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='faltas_eventos' AND COLUMN_NAME='origem');
SET @sql := IF(@has_ev>0 AND @col=0,
  "ALTER TABLE `faltas_eventos` ADD COLUMN `origem` ENUM('manual','diario') NOT NULL DEFAULT 'manual' AFTER `materias_json`",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
