-- Rollback: remove 'pdf' do enum de tipo de mensagem da Tudinha.
-- Atenção: se já existir alguma linha com tipo='pdf', este rollback falha
-- (MySQL não permite reduzir um ENUM removendo um valor em uso) — apagar ou
-- migrar essas linhas para outro tipo antes de rodar.

ALTER TABLE tudinha_mensagens
    MODIFY COLUMN tipo ENUM('texto', 'imagem', 'audio') COLLATE utf8mb4_unicode_ci DEFAULT 'texto';
