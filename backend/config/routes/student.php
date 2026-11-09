<?php
// Rotas para Alunos (perfil: aluno)
$router->post('/api/aluno/presenca', 'Api/StudentPresenceController@heartbeat');

$router->get('/dashboard', 'User/StudentController@dashboard');
$router->get('/grade-horaria', 'Education/GradeHorariaConsultaController@aluno');
$router->get('/grade-horaria/pdf', 'Education/GradeHorariaConsultaController@pdfAluno');
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
// EducaLabs, jogos e notes → routes.php de cada módulo


// Educa Livros (Google Books)
$router->get('/livros', 'Integrations/GoogleBooksController@index');
$router->get('/livros/buscar', 'Integrations/GoogleBooksController@buscar');
$router->get('/livros/destaques', 'Integrations/GoogleBooksController@destaques');
$router->get('/livros/{id}', 'Integrations/GoogleBooksController@detalhes');

// Meu Caderno → Modulos/notes/routes.php


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

// Jogos → Modulos/jogos/routes.php


// Avatar
$router->get('/avatar', 'User/AvatarController@index');
$router->post('/avatar/selecionar', 'User/AvatarController@salvarSelecionado');

// Onboarding
$router->get('/perfil', 'User/StudentController@perfil');
$router->get('/onboarding/verificar', 'User/OnboardingController@verificarOnboarding');
$router->post('/onboarding/salvar', 'User/OnboardingController@salvar');
$router->get('/onboarding/buscar', 'User/OnboardingController@buscar');

// Tickets → Modulos/tickets/routes.php


// Exercícios → Modulos/exercicios/routes.php


// Provas Online (Aluno)
$router->post('/aluno/provas/voz-para-texto', 'User/StudentController@vozParaTexto');

// Planos de aula e aulas online → módulos jornadas e aulas-online

// Apostilas → Modulos/apostilas/routes.php

// Meu Material / IA da Apostila (módulo novo, distinto do Apostilas simples acima)
// Minicursos → Modulos/minicursos/routes.php

// EducaHits → Modulos/educahits/routes.php


// Exercícios personalizados → Modulos/exercicios/routes.php

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

// Relatórios antigos do aluno: a classe RelatorioController não existe.
// O desempenho vigente está em /desempenho/jornadas e /desempenho/provas.
$router->get('/relatorios', function () {
    header('Location: ' . URL . '/desempenho/jornadas');
    exit;
});
$router->get('/relatorios/desempenho', function () {
    header('Location: ' . URL . '/desempenho/jornadas');
    exit;
});
$router->get('/relatorios/jornada', function () {
    header('Location: ' . URL . '/desempenho/jornadas');
    exit;
});
$router->get('/relatorios/redacao', function () {
    header('Location: ' . URL . '/redacoes');
    exit;
});

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
