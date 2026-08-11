<?php
// Rotas para Pais (perfil: pai)
$router->get('/pais/selecionar-filho', 'User/ParentController@selecionarFilho');
$router->get('/pais/dashboard', 'User/ParentController@dashboard');
$router->get('/pais/filhos', 'User/ParentController@filhos');
$router->get('/pais/filhos/{id}', 'User/ParentController@filhoDetalhes');
$router->get('/pais/filhos/{id}/notas', 'User/ParentController@notasFilho');
$router->get('/pais/filhos/{id}/desempenho', 'User/ParentController@desempenhoFilho');
$router->get('/pais/filhos/{id}/jornadas', 'User/ParentController@jornadasFilho');
$router->get('/pais/filhos/{id}/plano-aula', 'User/ParentController@planoAulaFilho');
$router->get('/pais/filhos/{id}/plano-aula/visualizar/{planoId}', 'User/ParentController@visualizarPlanoAulaFilho');
$router->get('/pais/filhos/{id}/plano-aula/pdf/{planoId}', 'User/ParentController@pdfPlanoAulaFilho');
$router->get('/pais/filhos/{id}/redacoes', 'User/ParentController@redacoesFilho');
$router->get('/pais/filhos/{id}/relatorios', 'User/ParentController@relatoriosFilho');
$router->get('/pais/filhos/{id}/financeiro', 'User/ParentController@financeiroFilho');

// Comunicação
$router->get('/pais/mensagens', 'User/ParentController@mensagens');
$router->post('/pais/mensagens', 'User/ParentController@enviarMensagem');
$router->get('/pais/notificacoes', 'User/ParentController@notificacoes');

// ==============================================
// NOTIFICAÇÕES PUSH (OneSignal)
// ==============================================
$router->get('/api/notificacoes-push/meu-tags', 'Api/OneSignalTagsController@index');
$router->get('/admin/notificacoes-push', 'Notifications/PushNotificationController@index');
$router->get('/admin/notificacoes-push/criar', 'Notifications/PushNotificationController@create');
$router->post('/admin/notificacoes-push/enviar', 'Notifications/PushNotificationController@enviar');
$router->get('/admin/notificacoes-push/{id}', 'Notifications/PushNotificationController@show');

// ==============================================
// SISTEMA DE NOTIFICAÇÕES
// ==============================================

// Rotas para Admin - Gerenciar Notificações
$router->get('/admin/notifications', 'Notifications/NotificationController@index');
$router->get('/admin/notifications/create', 'Notifications/NotificationController@create');
$router->post('/admin/notifications/store', 'Notifications/NotificationController@store');
$router->get('/admin/notifications/{id}', 'Notifications/NotificationController@show');
$router->get('/admin/notifications/{id}/edit', 'Notifications/NotificationController@edit');
$router->post('/admin/notifications/{id}/update', 'Notifications/NotificationController@update');
$router->get('/admin/notifications/{id}/delete', 'Notifications/NotificationController@delete');

// API para Admin - Notificações
$router->get('/admin/notifications/api/nao-lidas', 'Notifications/NotificationController@apiNaoLidas');
$router->post('/admin/notifications/api/marcar-lida', 'Notifications/NotificationController@apiMarcarLida');

// Rotas para Professor - Notificações para Turma
$router->get('/professor/notifications', 'Teacher/TeacherNotificationController@index');
$router->get('/professor/notifications/create', 'Teacher/TeacherNotificationController@create');
$router->post('/professor/notifications/store', 'Teacher/TeacherNotificationController@store');
$router->get('/professor/notifications/{id}', 'Teacher/TeacherNotificationController@show');
$router->get('/professor/notifications/{id}/delete', 'Teacher/TeacherNotificationController@delete');

// Rotas para Todos os Usuários - Visualizar Notificações
$router->get('/notifications', 'Notifications/UserNotificationController@index');
$router->get('/notifications/atualizar', 'Notifications/UserNotificationController@atualizar');
$router->get('/notifications/{id}', 'Notifications/UserNotificationController@show');
$router->get('/notifications/{id}/marcar-lida', 'Notifications/UserNotificationController@marcarLida');

// API para Todos os Usuários - Notificações
$router->get('/notifications/api/nao-lidas', 'Notifications/UserNotificationController@apiNaoLidas');
$router->get('/notifications/api/stream', 'Notifications/UserNotificationController@apiStream');
$router->post('/notifications/api/marcar-lida', 'Notifications/UserNotificationController@apiMarcarLida');
$router->get('/notifications/api/recentes', 'Notifications/UserNotificationController@apiRecentes');
$router->post('/notifications/api/marcar-todas-lidas', 'Notifications/UserNotificationController@apiMarcarTodasLidas');

// Calendário letivo
$router->get('/pais/calendario-letivo', 'User/ParentController@calendarioLetivo');
