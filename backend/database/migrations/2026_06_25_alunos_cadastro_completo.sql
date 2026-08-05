-- Migration: Cadastro completo de alunos (Fase 1)
-- Adiciona campos de identificacao civil em `alunos` e flags/dados de vinculo em `alunos_responsaveis`.
-- Idempotente: usa coluna-sentinela para evitar erro em re-execucao.

-- ============================================================
-- alunos: identificacao civil completa
-- Sentinela: nome_social
-- ============================================================
SET @has_alunos_civil := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'nome_social'
);

SET @sql_alunos_civil := IF(@has_alunos_civil > 0,
    'SELECT 1',
    'ALTER TABLE alunos
        ADD COLUMN nome_social VARCHAR(255) NULL AFTER nome,
        ADD COLUMN nacionalidade VARCHAR(60) NULL,
        ADD COLUMN naturalidade VARCHAR(120) NULL,
        ADD COLUMN uf_nascimento CHAR(2) NULL,
        ADD COLUMN cor_raca VARCHAR(20) NULL,
        ADD COLUMN orgao_emissor VARCHAR(30) NULL,
        ADD COLUMN uf_rg CHAR(2) NULL,
        ADD COLUMN certidao_nascimento VARCHAR(80) NULL,
        ADD COLUMN certidao_livro VARCHAR(20) NULL,
        ADD COLUMN certidao_folha VARCHAR(20) NULL,
        ADD COLUMN certidao_termo VARCHAR(20) NULL,
        ADD COLUMN nis VARCHAR(20) NULL,
        ADD COLUMN passaporte VARCHAR(30) NULL,
        ADD COLUMN rne VARCHAR(30) NULL,
        ADD COLUMN zona VARCHAR(10) NULL,
        ADD COLUMN pais VARCHAR(60) NULL,
        ADD COLUMN whatsapp VARCHAR(20) NULL,
        ADD COLUMN email_secundario VARCHAR(255) NULL'
);
PREPARE stmt_alunos_civil FROM @sql_alunos_civil;
EXECUTE stmt_alunos_civil;
DEALLOCATE PREPARE stmt_alunos_civil;

-- ============================================================
-- alunos_responsaveis: dados e flags do vinculo
-- Sentinela: parentesco
-- ============================================================
SET @has_resp_flags := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos_responsaveis' AND COLUMN_NAME = 'parentesco'
);

SET @sql_resp_flags := IF(@has_resp_flags > 0,
    'SELECT 1',
    'ALTER TABLE alunos_responsaveis
        ADD COLUMN parentesco VARCHAR(40) NULL,
        ADD COLUMN profissao VARCHAR(120) NULL,
        ADD COLUMN empresa VARCHAR(120) NULL,
        ADD COLUMN pode_retirar TINYINT(1) NOT NULL DEFAULT 0,
        ADD COLUMN recebe_boletos TINYINT(1) NOT NULL DEFAULT 0,
        ADD COLUMN recebe_boletim TINYINT(1) NOT NULL DEFAULT 0,
        ADD COLUMN recebe_notificacoes TINYINT(1) NOT NULL DEFAULT 0,
        ADD COLUMN responsavel_pedagogico TINYINT(1) NOT NULL DEFAULT 0,
        ADD COLUMN guarda_judicial TINYINT(1) NOT NULL DEFAULT 0,
        ADD COLUMN assina_documentos TINYINT(1) NOT NULL DEFAULT 0'
);
PREPARE stmt_resp_flags FROM @sql_resp_flags;
EXECUTE stmt_resp_flags;
DEALLOCATE PREPARE stmt_resp_flags;
