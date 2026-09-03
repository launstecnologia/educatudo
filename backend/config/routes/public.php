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
$router->get('/educalabs/public/{share_id}', 'EducaLabs/EducaLabsController@publicView');
$router->get('/educalabs/public/{share_id}/styles.css', 'EducaLabs/EducaLabsController@publicAsset');
$router->get('/educalabs/public/{share_id}/script.js', 'EducaLabs/EducaLabsController@publicAsset');
$router->get('/educalabs/validate-token', 'EducaLabs/EducaLabsController@validateToken');
$router->post('/educalabs/logout', 'EducaLabs/EducaLabsController@logoutToken');
$router->get('/games/validate-token', 'Games/GameController@validateToken');
$router->post('/games/logout', 'Games/GameController@logoutToken');
$router->get('/notes/validate-token', 'Notes/NotesTokenController@validateToken');
$router->post('/notes/logout', 'Notes/NotesTokenController@logoutToken');
$router->get('/external-apps/abrir/{id}', 'ExternalApps/ExternalAppsController@abrir');
$router->get('/external-apps/validate-token', 'ExternalApps/ExternalAppsController@validateToken');
$router->post('/external-apps/validate-token', 'ExternalApps/ExternalAppsController@validateToken');
$router->get('/external-apps/credit-context', 'ExternalApps/ExternalAppsController@creditContext');
$router->post('/external-apps/credit-context', 'ExternalApps/ExternalAppsController@creditContext');
$router->post('/external-apps/consume', 'ExternalApps/ExternalAppsController@consumeCredit');
$router->post('/external-apps/refund', 'ExternalApps/ExternalAppsController@refundCredit');
// AVA / EAD - Validação pública de certificado por código (sem login)
$router->get('/certificado/validar/{codigo}', 'Ava/CertificateValidationController@validate');

// Histórico Escolar — validação pública por hash (sem login; sem expor notas)
$router->get('/validar/historico/{hash}', 'Public/HistoricoValidacaoController@validar');

// Expo Colag — página pública do stand via QR (sem login)
$router->get('/expo-colag/s/{token}', 'Modulos/expo-colag/ExpoColagPublicoController@stand');
$router->post('/expo-colag/s/{token}/avaliar', 'Modulos/expo-colag/ExpoColagPublicoController@avaliarStand');
$router->get('/expo-colag/midia/{id}/capa', 'Modulos/expo-colag/ExpoColagPublicoController@capa');

$router->get('/manifest-aluno.json', 'Pwa/PwaController@manifestAluno');
$router->get('/manifest-professor.json', 'Pwa/PwaController@manifestProfessor');
$router->get('/manifest-admin.json', 'Pwa/PwaController@manifestAdmin');
$router->get('/manifest-pais.json', 'Pwa/PwaController@manifestPais');

// API de tracking de push (service worker - sem sessão)
$router->post('/api/webhook/gravacao-jitsi', 'Webhooks/JitsiRecordingWebhookController@handle');
$router->post('/api/notificacoes/visualizado', 'Api/PushTrackingController@visualizado');
$router->post('/api/notificacoes/clicado', 'Api/PushTrackingController@clicado');

// API Notícias (RSS) e Notificações manuais (API externa)
$router->get('/api/noticias', 'Noticias/NoticiasController@index');
$router->get('/api/notificacoes', 'Noticias/NotificacoesApiController@index');
$router->post('/api/notificacoes', 'Noticias/NotificacoesApiController@store');

// API REST Pais (Parent) - Login (público)
$router->post('/api/auth/login', 'Api/AuthController@login');

// API REST Pais - Documentação Swagger (público)
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
$router->get('/uploads/provas/questoes/{filename}', 'Exams/ServeExamQuestionImageController@serve');

// Rotas de autenticação
$router->get('/login/csrf-token', 'Auth/AuthController@getLoginCsrfTokenJson');
$router->get('/login', 'Auth/AuthController@loginAluno');
$router->post('/login', 'Auth/AuthController@autenticar');
$router->get('/logout', 'Auth/AuthController@logout');
$router->get('/auth/entrar-como', 'Auth/AuthController@entrarComo');
$router->get('/termos-de-uso', 'Auth/AuthController@termosDeUso');
$router->get('/politica-privacidade', 'Auth/AuthController@politicaPrivacidade');
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


// Captação pública de interesse + trilha do responsável (contrato)
$router->get('/matricula/interesse',                    'Modulos/matricula/MatriculaPublicoController@captacaoForm');
$router->post('/matricula/interesse',                   'Modulos/matricula/MatriculaPublicoController@captacaoStore');
$router->get('/matricula/contrato/{token}',              'Modulos/matricula/MatriculaPublicoController@verContrato');
$router->post('/matricula/contrato/{token}/dados',       'Modulos/matricula/MatriculaPublicoController@confirmarDados');
$router->post('/matricula/contrato/{token}/assinar',     'Modulos/matricula/MatriculaPublicoController@assinar');
$router->get('/matricula/contrato/{token}/pdf',          'Modulos/matricula/MatriculaPublicoController@downloadPdf');
$router->post('/webhooks/zapsign/{tenant_slug}',         'Modulos/matricula/ZapSignWebhookController@handle');
$router->post('/webhooks/zapsign',                       'Modulos/matricula/ZapSignWebhookController@handle');

// Catraca / portaria — Gestão de Presença (Bearer token da integração da escola)
$router->post('/api/webhooks/presenca',                  'Modulos/presenca/PresencaWebhookController@receber');
$router->post('/api/webhooks/presenca/{provedor}',       'Modulos/presenca/PresencaWebhookController@receber');
