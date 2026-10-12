<?php
// Rotas para Pais (perfil: pai)
$router->get('/pais/filhos/{id}/rematricula', 'Modulos/matricula/MatriculaPaisController@rematricula');
$router->post('/pais/filhos/{id}/rematricula', 'Modulos/matricula/MatriculaPaisController@confirmar');

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
$router->get('/pais/filhos/{id}/mural-recados', 'User/ParentController@muralRecadosFilho');
// Arquivos da turma (professor → aluno): rotas em app/Modulos/arquivos/routes.php
$router->get('/pais/filhos/{id}/drive', 'User/ParentController@driveFilho');
$router->get('/pais/filhos/{id}/drive/folder/{folderId}', 'User/ParentController@driveFilhoFolder');
$router->get('/pais/filhos/{id}/drive/view/{itemId}', 'User/ParentController@driveFilhoView');
$router->get('/pais/filhos/{id}/drive/download/{itemId}', 'User/ParentController@driveFilhoDownload');
$router->get('/pais/filhos/{id}/drive/serve/{itemId}', 'User/ParentController@driveFilhoServe');

// Comunicação
$router->get('/pais/mensagens', 'User/ParentController@mensagens');
$router->post('/pais/mensagens', 'User/ParentController@enviarMensagem');
$router->get('/pais/notificacoes', 'User/ParentController@notificacoes');

// ==============================================
// NOTIFICAÇÕES PUSH (OneSignal)
// ==============================================
$router->get('/api/notificacoes-push/meu-tags', 'Api/OneSignalTagsController@index');
// Notificações → Modulos/notificacoes/routes.php

// ==============================================
// SISTEMA DE NOTIFICAÇÕES
// ==============================================

// Rotas para Admin - Gerenciar Notificações

// API para Admin - Notificações

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
