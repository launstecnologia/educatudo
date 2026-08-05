-- Adiciona 'pdf' ao enum de tipo de mensagem da Tudinha (menu de anexos:
-- Tirar Foto / Enviar Imagem / Enviar PDF / Gravar Áudio). Executar em cada
-- banco de tenant.

-- Adicionar valor ao FIM de um ENUM é metadata-only no MySQL 8/InnoDB na
-- prática (não reescreve a tabela); ALGORITHM=INSTANT explícito junto com
-- LOCK=NONE nessa mesma cláusula não é uma combinação aceita pelo MySQL
-- pra MODIFY COLUMN (erro 1221) — deixa o servidor escolher sozinho.
ALTER TABLE tudinha_mensagens
    MODIFY COLUMN tipo ENUM('texto', 'imagem', 'audio', 'pdf') COLLATE utf8mb4_unicode_ci DEFAULT 'texto';
