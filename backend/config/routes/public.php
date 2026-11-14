<?php
// Rotas públicas (sem autenticação)
$router->get('/creditos/asaas/checkout', 'Master/MasterCreditosCheckoutController@iniciar');
$router->post('/creditos/asaas/checkout', 'Master/MasterCreditosCheckoutController@iniciar');
$router->get('/', 'Auth/AuthController@loginAluno');
$router->get('/admin', 'Auth/AuthController@loginAdmin');
$router->get('/professor', 'Auth/AuthController@loginProfessor');
$router->get('/professor/', 'Auth/AuthController@loginProfessor');
$router->get('/pais', 'Auth/AuthController@loginPais');
$router->get('/monitor', 'Auth/AuthController@loginMonitor');
$router->get('/monitor/', 'Auth/AuthController@loginMonitor');
// EducaLabs, jogos, notes, apps externos e notícias → Modulos/*/routes.public.php
// AVA certificados → Modulos/ava/routes.php


// Histórico Escolar — validação pública por hash (sem login; sem expor notas)
$router->get('/validar/historico/{hash}', 'Public/HistoricoValidacaoController@validar');
// Carteirinha pública → Modulos/carteirinha/routes.public.php


// Manual da implantação acadêmica (sem login; sem progresso nem dados da escola)
$router->get('/guia/implantar-academico', 'Public/GuiaAcademicoController@index');
// Gabarito, Expo, LEX, matrícula pública e webhook de presença → routes.public.php de cada módulo


$router->get('/manifest-aluno.json', 'Pwa/PwaController@manifestAluno');
$router->get('/manifest-professor.json', 'Pwa/PwaController@manifestProfessor');
$router->get('/manifest-admin.json', 'Pwa/PwaController@manifestAdmin');
$router->get('/manifest-pais.json', 'Pwa/PwaController@manifestPais');

// API de tracking de push (service worker - sem sessão)
$router->post('/api/webhook/gravacao-jitsi', 'Webhooks/JitsiRecordingWebhookController@handle');
// Notificações API → Modulos/notificacoes/routes.php

// API Notícias → Modulos/noticias/routes.public.php


// API REST Pais (Parent) - Login (público)
$router->post('/api/auth/login', 'Api/AuthController@login');

// API do responsável — Swagger
$router->get('/api/docs', 'Api/SwaggerController@index');
$router->get('/api/openapi.json', 'Api/SwaggerController@openapi');

// API de geração de imagens educacionais (OpenAI Images)
$router->post('/api/generate-image', 'Api/GenerateImageController@generate');

// API REST Pais - Rotas protegidas por JWT (ApiAuth)
$router->middleware('ApiAuth', function($router) {
    $router->get('/api/parents/children', 'Api/ParentController@children');
    $router->get('/api/parents/child/{id}/dashboard', 'Api/ParentController@dashboard');
    $router->get('/api/parents/child/{id}/exams', 'Api/ParentController@exams');
    $router->get('/api/parents/child/{id}/exercises', 'Api/ParentController@exercises');
    $router->get('/api/parents/child/{id}/journeys', 'Api/ParentController@journeys');
    $router->get('/api/parents/child/{id}/lesson-plans', 'Api/ParentController@lessonPlans');
    $router->get('/api/parents/child/{id}/essays', 'Api/ParentController@essays');
});

// Polling de jobs de IA (sessão validada no AIJobController; rota pública evita 404 em proxies/nginx)
$router->get('/ai-job/{id}/status', 'AIJobController@status');

// Mídia (layout, essays, redações) – URL estável para S3 ou local
$router->get('/media/serve', 'Media/MediaServeController@serve');

// Imagem de enunciado de exercício (jornadas) – rota pública para <img> do aluno
$router->get('/uploads/jornadas/exercicios/{filename}', 'Education/ServeImagemExercicioController@serve');

// Imagem de questão (provas) – rota pública para <img> no editor e na prova

// LEX → Modulos/lex/routes.public.php
$router->get('/login/csrf-token', 'Auth/AuthController@getLoginCsrfTokenJson');
$router->get('/login', 'Auth/AuthController@loginAluno');
$router->post('/login', 'Auth/AuthController@autenticar');
$router->get('/logout', 'Auth/AuthController@logout');
$router->get('/auth/entrar-como', 'Auth/AuthController@entrarComo');
$router->get('/termos-de-uso', 'Auth/AuthController@termosDeUso');
$router->get('/politica-privacidade', 'Auth/AuthController@politicaPrivacidade');
$router->get('/politica-privacidade-app', 'Auth/AuthController@politicaPrivacidadeAppFamilia');
$router->get('/app-familia/politica-privacidade', 'Auth/AuthController@politicaPrivacidadeAppFamilia');
$router->get('/politica-retencao', 'Auth/AuthController@politicaRetencao');
$router->get('/recuperar-senha', 'Auth/AuthController@recuperarSenha');
$router->post('/enviar-recuperacao', 'Auth/AuthController@enviarRecuperacao');
$router->get('/recuperar-senha/reset', 'Auth/AuthController@resetPassword');
$router->post('/recuperar-senha/reset', 'Auth/AuthController@processarReset');

// Primeiro acesso do aluno (GET = formulário; POST = valida na mesma URL, sem fetch)
$router->get('/primeiro-acesso', 'Auth/AuthController@primeiroAcesso');
$router->post('/primeiro-acesso', 'Auth/AuthController@primeiroAcesso');
$router->get('/primeiro-acesso/alunos', 'Auth/AuthController@buscarAlunosPorTurma');
$router->get('/primeiro-acesso/validar', 'Auth/AuthController@redirectPrimeiroAcesso'); // GET → redireciona para o formulário
$router->post('/primeiro-acesso/validar', 'Auth/AuthController@validarPrimeiroAcesso');
$router->get('/primeiro-acesso/criar', 'Auth/AuthController@criarAcesso');
$router->post('/primeiro-acesso/salvar', 'Auth/AuthController@salvarPrimeiroAcesso');
$router->get('/primeiro-acesso/sucesso', 'Auth/AuthController@sucessoPrimeiroAcesso');

// Recuperação de senha do aluno (sem e-mail)
$router->get('/aluno/recuperar-senha', 'Auth/AuthController@recuperarSenhaAluno');
$router->post('/aluno/recuperar-senha/pergunta', 'Auth/AuthController@recuperarSenhaAlunoPergunta');
$router->post('/aluno/recuperar-senha/reset', 'Auth/AuthController@recuperarSenhaAlunoReset');

// Recuperação de senha específica para professores
$router->get('/professor/recuperar-senha', 'Auth/AuthController@recuperarSenhaProfessor');
$router->post('/professor/enviar-recuperacao', 'Auth/AuthController@enviarRecuperacaoProfessor');
$router->get('/professor/recuperar-senha/reset', 'Auth/AuthController@resetPasswordProfessor');
$router->post('/professor/recuperar-senha/reset', 'Auth/AuthController@processarResetProfessor');

// Alteração de senha obrigatória na tela de login (sem autenticação)
$router->post('/auth/alterar-senha-obrigatoria', 'Auth/AuthController@alterarSenhaObrigatoriaLogin');

// Aceite obrigatório (usuário logado)
$router->get('/consent', 'Auth/AuthController@consent');
$router->post('/consent/accept', 'Auth/AuthController@acceptConsent');

// Matrícula pública e webhook de presença → routes.public.php de cada módulo
