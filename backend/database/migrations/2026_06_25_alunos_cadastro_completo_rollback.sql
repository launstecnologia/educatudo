-- Rollback: Cadastro completo de alunos (Fase 1)

SET @has_alunos_civil := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos' AND COLUMN_NAME = 'nome_social'
);
SET @sql_alunos_civil := IF(@has_alunos_civil = 0,
    'SELECT 1',
    'ALTER TABLE alunos
        DROP COLUMN nome_social,
        DROP COLUMN nacionalidade,
        DROP COLUMN naturalidade,
        DROP COLUMN uf_nascimento,
        DROP COLUMN cor_raca,
        DROP COLUMN orgao_emissor,
        DROP COLUMN uf_rg,
        DROP COLUMN certidao_nascimento,
        DROP COLUMN certidao_livro,
        DROP COLUMN certidao_folha,
        DROP COLUMN certidao_termo,
        DROP COLUMN nis,
        DROP COLUMN passaporte,
        DROP COLUMN rne,
        DROP COLUMN zona,
        DROP COLUMN pais,
        DROP COLUMN whatsapp,
        DROP COLUMN email_secundario'
);
PREPARE stmt_alunos_civil FROM @sql_alunos_civil;
EXECUTE stmt_alunos_civil;
DEALLOCATE PREPARE stmt_alunos_civil;

SET @has_resp_flags := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alunos_responsaveis' AND COLUMN_NAME = 'parentesco'
);
SET @sql_resp_flags := IF(@has_resp_flags = 0,
    'SELECT 1',
    'ALTER TABLE alunos_responsaveis
        DROP COLUMN parentesco,
        DROP COLUMN profissao,
        DROP COLUMN empresa,
        DROP COLUMN pode_retirar,
        DROP COLUMN recebe_boletos,
        DROP COLUMN recebe_boletim,
        DROP COLUMN recebe_notificacoes,
        DROP COLUMN responsavel_pedagogico,
        DROP COLUMN guarda_judicial,
        DROP COLUMN assina_documentos'
);
PREPARE stmt_resp_flags FROM @sql_resp_flags;
EXECUTE stmt_resp_flags;
DEALLOCATE PREPARE stmt_resp_flags;
