-- Garante alunos.nome_social e demais campos do cadastro civil em escolas
-- que marcaram 2026_06_25_alunos_cadastro_completo como executada sem o ALTER.
-- Tenant. Idempotente (uma coluna por vez). Rollback: 2026_09_02_alunos_nome_social_rollback.sql

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'nome_social'
        ),
        'ALTER TABLE alunos ADD COLUMN nome_social VARCHAR(255) NULL AFTER nome',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'nacionalidade'
        ),
        'ALTER TABLE alunos ADD COLUMN nacionalidade VARCHAR(60) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'naturalidade'
        ),
        'ALTER TABLE alunos ADD COLUMN naturalidade VARCHAR(120) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'uf_nascimento'
        ),
        'ALTER TABLE alunos ADD COLUMN uf_nascimento CHAR(2) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'cor_raca'
        ),
        'ALTER TABLE alunos ADD COLUMN cor_raca VARCHAR(20) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'orgao_emissor'
        ),
        'ALTER TABLE alunos ADD COLUMN orgao_emissor VARCHAR(30) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'uf_rg'
        ),
        'ALTER TABLE alunos ADD COLUMN uf_rg CHAR(2) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'certidao_nascimento'
        ),
        'ALTER TABLE alunos ADD COLUMN certidao_nascimento VARCHAR(80) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'certidao_livro'
        ),
        'ALTER TABLE alunos ADD COLUMN certidao_livro VARCHAR(20) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'certidao_folha'
        ),
        'ALTER TABLE alunos ADD COLUMN certidao_folha VARCHAR(20) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'certidao_termo'
        ),
        'ALTER TABLE alunos ADD COLUMN certidao_termo VARCHAR(20) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'nis'
        ),
        'ALTER TABLE alunos ADD COLUMN nis VARCHAR(20) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'passaporte'
        ),
        'ALTER TABLE alunos ADD COLUMN passaporte VARCHAR(30) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'rne'
        ),
        'ALTER TABLE alunos ADD COLUMN rne VARCHAR(30) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'zona'
        ),
        'ALTER TABLE alunos ADD COLUMN zona VARCHAR(10) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'pais'
        ),
        'ALTER TABLE alunos ADD COLUMN pais VARCHAR(60) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'whatsapp'
        ),
        'ALTER TABLE alunos ADD COLUMN whatsapp VARCHAR(20) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        NOT EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'email_secundario'
        ),
        'ALTER TABLE alunos ADD COLUMN email_secundario VARCHAR(255) NULL',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
