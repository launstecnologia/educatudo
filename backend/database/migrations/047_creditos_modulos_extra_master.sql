-- MASTER apenas (nome do arquivo deve conter "master" — não roda no tenant).
-- INSERT em modulos_preco_creditos existe só no banco administrador.

INSERT IGNORE INTO `modulos_preco_creditos` (`modulo_key`, `creditos`, `nome_exibicao`, `ativo`) VALUES
('tudinha_chat_imagem', 1, 'Chat com imagem', 1),
('flashcard', 1, 'FlashCard', 1),
('flashcard_nao_entendi', 1, 'FlashCard (explicação)', 1),
('redacao_gerar_tema_aluno', 1, 'Geração de tema (redação)', 1);
