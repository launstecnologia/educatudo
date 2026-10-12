<?php
// Rotas para Alunos (perfil: aluno)
$router->post('/api/aluno/presenca', 'Api/StudentPresenceController@heartbeat');

$router->get('/dashboard', 'User/StudentController@dashboard');
$router->get('/dashboard/agenda', 'User/StudentController@dashboardAgendaJson');
$router->get('/dashboard/api/montar', 'User/StudentController@dashboardApiMontar');
$router->post('/dashboard/trocar-turma', 'User/StudentController@trocarTurmaCurso');
$router->get('/desempenho/jornadas', 'User/StudentController@desempenhoJornadas');
$router->get('/desempenho/provas', 'User/StudentController@desempenhoProvas');
$router->get('/notas-boletins', 'User/StudentController@notasBoletins');
$router->get('/notas', 'User/StudentController@notas');
$router->get('/boletim', 'User/StudentController@boletim');
$router->get('/carteira', 'User/StudentController@carteira');
$router->get('/educashop', 'User/StudentController@educaShop');
$router->post('/educashop', 'User/StudentController@educaShopComprar');
$router->get('/carteira/comprar', 'User/StudentController@carteiraComprar');
$router->post('/carteira/comprar', 'User/StudentController@carteiraComprar');
$router->get('/carteira/comprar/aguardando/{id}', 'User/StudentController@carteiraComprarAguardando');
$router->post('/carteira/comprar/pagar/{id}', 'User/StudentController@carteiraComprarPagar');
$router->post('/carteira/comprar/verificar/{id}', 'User/StudentController@carteiraComprarVerificar');
$router->post('/carteira/comprar/status/{id}', 'User/StudentController@carteiraComprarStatus');
$router->get('/carteira/comprar/simular/{id}', 'User/StudentController@carteiraComprarSimular');
$router->get('/carteira/planos', 'User/StudentController@carteiraPlanos');
$router->post('/carteira/planos/assinar', 'User/StudentController@carteiraPlanosAssinar');
$router->get('/mural-recados', 'User/StudentController@muralRecados');
$router->post('/mural-recados/marcar-visto', 'User/StudentController@marcarVistoMuralRecado');
// Flashcards → Modulos/flashcards/routes.php
// Fórum → Modulos/forum/routes.php
// Fórum – moderação (admin)
// Drive: rotas em app/Modulos/drive/routes.php
// EducaLabs
$router->get('/educalabs/access', 'EducaLabs/EducaLabsController@access');
$router->get('/jogos/access', 'Games/GameController@access');
$router->get('/notes/access', 'Notes/NotesController@access');
$router->get('/notes/access-url', 'Notes/NotesController@accessUrl');
$router->get('/educalabs', 'EducaLabs/EducaLabsController@index');
$router->get('/educalabs/novo', 'EducaLabs/EducaLabsController@create');
$router->post('/educalabs/salvar', 'EducaLabs/EducaLabsController@store');
$router->get('/educalabs/projetos/{id}', 'EducaLabs/EducaLabsController@show');
$router->get('/educalabs/projetos/{id}/preview', 'EducaLabs/EducaLabsController@preview');
$router->get('/educalabs/projetos/{id}/preview/{file}', 'EducaLabs/EducaLabsController@previewFile');
$router->post('/educalabs/projetos/{id}/mensagem', 'EducaLabs/EducaLabsController@addMessage');
$router->post('/educalabs/projetos/{id}/gerar', 'EducaLabs/EducaLabsController@generate');

// Educa Livros (Google Books)
$router->get('/livros', 'Integrations/GoogleBooksController@index');
$router->get('/livros/buscar', 'Integrations/GoogleBooksController@buscar');
$router->get('/livros/destaques', 'Integrations/GoogleBooksController@destaques');
$router->get('/livros/{id}', 'Integrations/GoogleBooksController@detalhes');

// Meu Caderno (aluno: anotações com título, matéria, pastas de estudo e anexos)
$router->get('/caderno', 'User/CadernoController@index');
$router->get('/caderno/novo', 'User/CadernoController@create');
$router->get('/caderno/novo-excalidraw', 'User/CadernoController@createExcalidraw');
$router->post('/caderno', 'User/CadernoController@store');
$router->post('/caderno/pasta', 'User/CadernoController@storePasta');
$router->post('/caderno/pasta/{id}', 'User/CadernoController@updatePasta');
$router->post('/caderno/pasta/{id}/excluir', 'User/CadernoController@destroyPasta');
$router->get('/caderno/{id}', 'User/CadernoController@show');
$router->get('/caderno/{id}/editar', 'User/CadernoController@edit');
$router->get('/caderno/{id}/excalidraw-editor', 'User/CadernoController@excalidrawEditor');
$router->get('/caderno/{id}/excalidraw-view', 'User/CadernoController@excalidrawView');
$router->get('/caderno/{id}/excalidraw-carregar', 'User/CadernoController@excalidrawCarregar');
$router->post('/caderno/{id}/excalidraw-salvar', 'User/CadernoController@excalidrawSalvar');
$router->post('/caderno/{id}', 'User/CadernoController@update');
$router->post('/caderno/{id}/excluir', 'User/CadernoController@destroy');
$router->post('/caderno/anexo/excluir', 'User/CadernoController@excluirAnexo');
$router->post('/caderno/anexo/salvar-anotacao', 'User/CadernoController@salvarAnotacaoAnexo');
$router->get('/caderno/{caderno_id}/anexo/{anexo_id}/anotar', 'User/CadernoController@anotarAnexo');
$router->get('/caderno/{caderno_id}/anexo/{anexo_id}', 'User/CadernoController@anexo');

// Alteração obrigatória de senha
$router->get('/aluno/alterar-senha-obrigatoria', 'User/StudentController@alterarSenhaObrigatoria');
$router->post('/aluno/alterar-senha-obrigatoria', 'User/StudentController@processarAlteracaoSenha');

// Alteração obrigatória de senha do professor
$router->get('/professor/alterar-senha-obrigatoria', 'User/TeacherController@alterarSenhaObrigatoria');
$router->post('/professor/alterar-senha-obrigatoria', 'User/TeacherController@processarAlteracaoSenha');
$router->get('/pais/alterar-senha-obrigatoria', 'User/ParentController@alterarSenhaObrigatoria');
$router->post('/pais/alterar-senha-obrigatoria', 'User/ParentController@processarAlteracaoSenha');

// Chat Tudinha
$router->get('/chat', 'User/StudentController@chat');
$router->post('/chat/conversa', 'User/StudentController@createConversation');
$router->post('/chat/mensagem', 'User/StudentController@sendMessage');
$router->get('/chat/mensagem-stream', 'User/StudentController@sendMessageStream');
$router->post('/chat/mensagem-stream', 'User/StudentController@sendMessageStream');
$router->post('/chat/upload-imagem', 'User/StudentController@uploadImage');
$router->post('/chat/upload-pdf', 'User/StudentController@uploadPdf');
$router->post('/chat/upload-arquivo', 'User/StudentController@uploadArquivo');
$router->post('/chat/gerar-imagem', 'User/StudentController@gerarImagem');
$router->post('/chat/flashcard-concluido', 'User/StudentController@flashcardConcluido');
$router->post('/chat/texto-para-voz', 'User/StudentController@textoParaVoz');
$router->post('/chat/voz-para-texto', 'User/StudentController@vozParaTexto');
$router->post('/chat/voz-realtime/iniciar', 'User/StudentController@iniciarSessaoVoz');
$router->post('/chat/voz-realtime/salvar-transcricao', 'User/StudentController@salvarTranscricaoVoz');
$router->get('/chat/mensagens', 'User/StudentController@getMessages');
$router->get('/chat/ver-imagem', 'User/StudentController@verImagemChat');
$router->get('/chat/conversa-info', 'User/StudentController@getConversationInfo');
$router->post('/chat/conversa/excluir', 'User/StudentController@deleteConversation');

// Inglês - Speaking
$router->get('/ingles', 'Integrations/EnglishController@index');
$router->get('/ingles/conversa/{id}', 'Integrations/EnglishController@conversaMensagens');
$router->post('/ingles/transcrever-audio', 'Integrations/EnglishController@transcreverAudio');
$router->post('/ingles/conversar', 'Integrations/EnglishController@conversar');
$router->post('/ingles/traduzir', 'Integrations/EnglishController@traduzir');
$router->get('/ingles/historico', 'Integrations/EnglishController@historico');

// Jogos
$router->get('/jogos', 'Games/GameController@index');

// Jogo do Milhão
$router->get('/jogo-milhao', 'Games/MillionGameController@index');
$router->get('/jogo-milhao/jogar', 'Games/MillionGameController@jogar');
$router->post('/jogo-milhao/iniciar', 'Games/MillionGameController@iniciarPartida');
$router->post('/jogo-milhao/continuar', 'Games/MillionGameController@continuarPartida');
$router->post('/jogo-milhao/responder', 'Games/MillionGameController@responderPergunta');
$router->post('/jogo-milhao/ajuda', 'Games/MillionGameController@usarAjuda');
$router->post('/jogo-milhao/abandonar', 'Games/MillionGameController@abandonar');
$router->post('/jogo-milhao/heartbeat', 'Games/MillionGameController@heartbeat');
$router->post('/jogo-milhao/verificar-partida', 'Games/MillionGameController@verificarPartida');
$router->post('/jogo-milhao/limpar-orfas', 'Games/MillionGameController@limparOrfas');

// Avatar
$router->get('/avatar', 'User/AvatarController@index');
$router->post('/avatar/selecionar', 'User/AvatarController@salvarSelecionado');

// Onboarding
$router->get('/perfil', 'User/StudentController@perfil');
$router->get('/onboarding/verificar', 'User/OnboardingController@verificarOnboarding');
$router->post('/onboarding/salvar', 'User/OnboardingController@salvar');
$router->get('/onboarding/buscar', 'User/OnboardingController@buscar');

// Tickets de Suporte (Aluno)
$router->get('/tickets', 'Support/SupportTicketController@index');
$router->get('/tickets/criar', 'Support/SupportTicketController@criar');
$router->post('/tickets/processar-criar', 'Support/SupportTicketController@processarCriar');
$router->get('/tickets/visualizar', 'Support/SupportTicketController@visualizar');
$router->post('/tickets/enviar-mensagem', 'Support/SupportTicketController@enviarMensagem');
$router->post('/tickets/upload-arquivo', 'Support/SupportTicketController@uploadArquivo');
$router->post('/tickets/upload-imagem', 'Support/SupportTicketController@uploadImagem');

// Exercícios
$router->get('/exercicios', 'Exercises/ExerciseController@index');
$router->get('/exercicios/iniciar', 'Exercises/ExerciseController@iniciar');
$router->post('/exercicios/responder', 'Exercises/ExerciseController@responder');
$router->get('/exercicios/finalizar', 'Exercises/ExerciseController@finalizar');
$router->post('/exercicios/finalizar', 'Exercises/ExerciseController@finalizar');
$router->get('/exercicios/resultado', 'Exercises/ExerciseController@resultado');
$router->get('/exercicios/historico', 'Exercises/ExerciseController@historico');

// Provas Online (Aluno)
$router->post('/aluno/provas/voz-para-texto', 'User/StudentController@vozParaTexto');

// Planos de Aula (Aluno)
$router->get('/aluno/planos-aula', 'User/StudentController@planosAula');
$router->get('/aluno/planos-aula/visualizar/{id}', 'User/StudentController@visualizarPlanoAula');
// Arquivos/Recuperação: rotas em app/Modulos/arquivos/routes.php
$router->get('/aluno/aulas-online', 'User/StudentOnlineClassController@index');
$router->get('/aluno/aulas-online/mensagens', 'User/StudentOnlineClassController@mensagens');
$router->post('/aluno/aulas-online/enviar-mensagem', 'User/StudentOnlineClassController@enviarMensagem');
$router->get('/aluno/aulas-online/gravacao/{id}', 'User/StudentOnlineClassController@gravacao');
$router->get('/aluno/aulas-online/{id}', 'User/StudentOnlineClassController@show');
// Apostilas → Modulos/apostilas/routes.php

// Meu Material / IA da Apostila (módulo novo, distinto do Apostilas simples acima)
// Minicursos → Modulos/minicursos/routes.php

// EducaHits — redirecionamento ao portal externo
$router->get('/hits', 'EducaHits/EducaHitsPortalController@toPortal');
$router->get('/hits/solicitar', 'EducaHits/EducaHitsPortalController@toSolicitar');
$router->get('/hits/demo-player', 'EducaHits/EducaHitsPortalController@toPortal');
$router->get('/hits/request', 'EducaHits/EducaHitsPortalController@toSolicitar');
$router->post('/hits/request', 'EducaHits/EducaHitsPortalController@submitRequest');
$router->get('/hits/my-requests', 'EducaHits/EducaHitsPortalController@toPortal');
$router->get('/hits/track/{id}', 'EducaHits/EducaHitsPortalController@toPortal');

// Exercícios Personalizados
$router->get('/exercicios-personalizados', 'Exercises/CustomExerciseController@index');
$router->get('/exercicios-personalizados/criar', 'Exercises/CustomExerciseController@criar');
$router->post('/exercicios-personalizados/gerar', 'Exercises/CustomExerciseController@gerarExercicios');
$router->post('/exercicios-personalizados/importar-questoes-ia/{jobId}', 'Exercises/CustomExerciseController@importarQuestoesIA');
$router->post('/exercicios-personalizados/erro-geracao-ia', 'Exercises/CustomExerciseController@erroGeracaoIA');
$router->post('/exercicios-personalizados/tentar-novamente/{listaId}', 'Exercises/CustomExerciseController@tentarNovamente');
$router->get('/exercicios-personalizados/minhas-listas', 'Exercises/CustomExerciseController@listaMinhasListas');
$router->get('/exercicios-personalizados/status', 'Exercises/CustomExerciseController@verificarStatus');
$router->post('/exercicios-personalizados/iniciar', 'Exercises/CustomExerciseController@iniciarExecucao');
$router->get('/exercicios-personalizados/executar', 'Exercises/CustomExerciseController@executar');
$router->post('/exercicios-personalizados/executar', 'Exercises/CustomExerciseController@executar');
$router->post('/exercicios-personalizados/responder', 'Exercises/CustomExerciseController@responderQuestao');
$router->post('/exercicios-personalizados/finalizar', 'Exercises/CustomExerciseController@finalizar');
$router->get('/exercicios-personalizados/finalizar', 'Exercises/CustomExerciseController@finalizar');
$router->get('/exercicios-personalizados/resultados', 'Exercises/CustomExerciseController@resultados');
$router->get('/exercicios-personalizados/historico', 'Exercises/CustomExerciseController@historico');

// Redações → Modulos/redacoes/routes.php

// Redação Configurável (Aluno) – rotas legadas

// Jornada da Redação (Aluno) – URL canônica

// Chat Global (Aluno)
$router->get('/chat-professor', 'Integrations/ChatController@indexAluno');
$router->get('/chat-professor/{professor_id}', 'Integrations/ChatController@chatAluno');
$router->post('/chat-professor/enviar-mensagem', 'Integrations/ChatController@enviarMensagemAluno');
$router->get('/chat-professor/buscar-mensagens', 'Integrations/ChatController@buscarMensagensAluno');

// Jornadas → Modulos/jornadas/routes.php
// Redações da Jornada (Aluno)

// Simulados ENEM

$router->get('/jogos', 'Games/GameController@index');
$router->get('/jogos/xadrez', 'Games/GameController@xadrez');
$router->get('/jogos/damas', 'Games/GameController@damas');
$router->get('/jogos/milhao', 'Games/GameController@milhao');

$router->get('/relatorios', 'RelatorioController@index');
$router->get('/relatorios/desempenho', 'RelatorioController@desempenho');
$router->get('/relatorios/jornada', 'RelatorioController@jornada');
$router->get('/relatorios/redacao', 'RelatorioController@redacao');

// AVA aluno → Modulos/ava/routes.php
// AVA → Modulos/ava/routes.php

// AVA / EAD - Aluno: atividades/tarefas (Fase 2)
// AVA / EAD - Aluno: comentários por aula (Fase 2)

// AVA / EAD - Aluno: aulas ao vivo (Fase 3)

// AVA / EAD - Aluno: certificados de conclusão (Fase 3)


// Calendário letivo
$router->get('/calendario-letivo', 'User/StudentController@calendarioLetivo');
$router->get('/agenda', 'Student/AgendaController@agenda');
$router->post('/agenda/item', 'Student/AgendaController@criarItemPessoal');
$router->post('/agenda/item/{id}/editar', 'Student/AgendaController@editarItemPessoal');
$router->post('/agenda/item/{id}/excluir', 'Student/AgendaController@excluirItemPessoal');
