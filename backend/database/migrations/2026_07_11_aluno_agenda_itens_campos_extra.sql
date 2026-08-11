-- Agenda do aluno: campos extras no item pessoal (tipo, hora, banca/instituição,
-- local, link) — formulário de cadastro mais completo. Executar em cada banco
-- de tenant, depois de 2026_07_11_aluno_agenda_itens.sql.

SET @col_tipo := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aluno_agenda_itens' AND COLUMN_NAME = 'tipo'
);
SET @sql_tipo := IF(
    @col_tipo = 0,
    'ALTER TABLE aluno_agenda_itens ADD COLUMN tipo VARCHAR(30) NOT NULL DEFAULT ''pessoal'' AFTER titulo',
    'SELECT 1'
);
PREPARE stmt_tipo FROM @sql_tipo;
EXECUTE stmt_tipo;
DEALLOCATE PREPARE stmt_tipo;

SET @col_hora := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aluno_agenda_itens' AND COLUMN_NAME = 'hora'
);
SET @sql_hora := IF(
    @col_hora = 0,
    'ALTER TABLE aluno_agenda_itens ADD COLUMN hora TIME NULL AFTER data',
    'SELECT 1'
);
PREPARE stmt_hora FROM @sql_hora;
EXECUTE stmt_hora;
DEALLOCATE PREPARE stmt_hora;

SET @col_banca := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aluno_agenda_itens' AND COLUMN_NAME = 'banca'
);
SET @sql_banca := IF(
    @col_banca = 0,
    'ALTER TABLE aluno_agenda_itens ADD COLUMN banca VARCHAR(255) NULL AFTER hora',
    'SELECT 1'
);
PREPARE stmt_banca FROM @sql_banca;
EXECUTE stmt_banca;
DEALLOCATE PREPARE stmt_banca;

SET @col_local := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aluno_agenda_itens' AND COLUMN_NAME = 'local'
);
SET @sql_local := IF(
    @col_local = 0,
    'ALTER TABLE aluno_agenda_itens ADD COLUMN local VARCHAR(255) NULL AFTER banca',
    'SELECT 1'
);
PREPARE stmt_local FROM @sql_local;
EXECUTE stmt_local;
DEALLOCATE PREPARE stmt_local;

SET @col_link := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'aluno_agenda_itens' AND COLUMN_NAME = 'link'
);
SET @sql_link := IF(
    @col_link = 0,
    'ALTER TABLE aluno_agenda_itens ADD COLUMN link VARCHAR(500) NULL AFTER local',
    'SELECT 1'
);
PREPARE stmt_link FROM @sql_link;
EXECUTE stmt_link;
DEALLOCATE PREPARE stmt_link;
