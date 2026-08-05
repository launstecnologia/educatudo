-- Reuniões com pais (por aluno) e reuniões gerais da escola

CREATE TABLE IF NOT EXISTS `reunioes` (
    `id`            INT NOT NULL AUTO_INCREMENT,
    `tipo`          ENUM('pais','geral') NOT NULL DEFAULT 'pais',
    `titulo`        VARCHAR(255) NOT NULL,
    `data_reuniao`  DATE NOT NULL,
    `hora_inicio`   TIME NULL,
    `hora_fim`      TIME NULL,
    `local_reuniao` VARCHAR(255) NULL,
    `descricao`     TEXT NULL,
    `aluno_id`      INT NULL,           -- preenchido apenas no tipo 'pais'
    `responsavel_nome` VARCHAR(255) NULL, -- quem compareceu (tipo 'pais')
    `criado_por`    INT NULL,           -- admin_id
    `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_aluno` (`aluno_id`),
    KEY `idx_tipo_data` (`tipo`, `data_reuniao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reuniao_turmas` (
    `reuniao_id` INT NOT NULL,
    `turma_id`   INT NOT NULL,
    PRIMARY KEY (`reuniao_id`, `turma_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reuniao_anexos` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `reuniao_id` INT NOT NULL,
    `nome`       VARCHAR(255) NOT NULL,
    `caminho`    VARCHAR(500) NOT NULL,
    `mime`       VARCHAR(100) NULL,
    `tamanho`    INT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_reuniao` (`reuniao_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
