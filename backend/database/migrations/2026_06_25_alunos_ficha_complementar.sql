-- Migration: Ficha complementar do aluno (Fase 2)
-- Saude, alimentacao e transporte escolar (relacao 1:1 com alunos).

CREATE TABLE IF NOT EXISTS `alunos_ficha_complementar` (
    `aluno_id` INT(11) NOT NULL,
    `tipo_sanguineo` VARCHAR(5) NULL,
    `alergias` TEXT NULL,
    `medicamentos_uso` TEXT NULL,
    `condicoes_cronicas` TEXT NULL,
    `deficiencias_obs` TEXT NULL,
    `plano_saude` VARCHAR(120) NULL,
    `plano_saude_numero` VARCHAR(60) NULL,
    `hospital_referencia` VARCHAR(160) NULL,
    `contato_emergencia_nome` VARCHAR(160) NULL,
    `contato_emergencia_telefone` VARCHAR(20) NULL,
    `contato_emergencia_parentesco` VARCHAR(40) NULL,
    `restricoes_alimentares` TEXT NULL,
    `alimentacao_obs` TEXT NULL,
    `usa_transporte_escolar` TINYINT(1) NOT NULL DEFAULT 0,
    `transporte_tipo` VARCHAR(20) NULL,
    `transporte_rota` VARCHAR(120) NULL,
    `transporte_ponto` VARCHAR(160) NULL,
    `transporte_responsavel` VARCHAR(160) NULL,
    `transporte_telefone` VARCHAR(20) NULL,
    `observacoes_gerais` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`aluno_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
