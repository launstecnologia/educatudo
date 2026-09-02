-- Rollback de 2026_09_02_alunos_nome_social.sql
-- DROP/DELETE: desfaz só as colunas desta correção. Escolas que já tinham
-- o cadastro civil pela 2026_06_25 perdem os mesmos campos se este rollback rodar.

SET @db := DATABASE();

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'email_secundario'
        ),
        'ALTER TABLE alunos DROP COLUMN email_secundario',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'whatsapp'
        ),
        'ALTER TABLE alunos DROP COLUMN whatsapp',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'pais'
        ),
        'ALTER TABLE alunos DROP COLUMN pais',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'zona'
        ),
        'ALTER TABLE alunos DROP COLUMN zona',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'rne'
        ),
        'ALTER TABLE alunos DROP COLUMN rne',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'passaporte'
        ),
        'ALTER TABLE alunos DROP COLUMN passaporte',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'nis'
        ),
        'ALTER TABLE alunos DROP COLUMN nis',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'certidao_termo'
        ),
        'ALTER TABLE alunos DROP COLUMN certidao_termo',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'certidao_folha'
        ),
        'ALTER TABLE alunos DROP COLUMN certidao_folha',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'certidao_livro'
        ),
        'ALTER TABLE alunos DROP COLUMN certidao_livro',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'certidao_nascimento'
        ),
        'ALTER TABLE alunos DROP COLUMN certidao_nascimento',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'uf_rg'
        ),
        'ALTER TABLE alunos DROP COLUMN uf_rg',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'orgao_emissor'
        ),
        'ALTER TABLE alunos DROP COLUMN orgao_emissor',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'cor_raca'
        ),
        'ALTER TABLE alunos DROP COLUMN cor_raca',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'uf_nascimento'
        ),
        'ALTER TABLE alunos DROP COLUMN uf_nascimento',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'naturalidade'
        ),
        'ALTER TABLE alunos DROP COLUMN naturalidade',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'nacionalidade'
        ),
        'ALTER TABLE alunos DROP COLUMN nacionalidade',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := (
    SELECT IF(
        EXISTS (
            SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'nome_social'
        ),
        'ALTER TABLE alunos DROP COLUMN nome_social',
        'SELECT 1'
    )
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
