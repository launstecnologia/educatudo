<?php
/**
 * Rotas do módulo notificacoes.
 *
 * @var Router $router
 */

$router->get('/admin/notificacoes-push', 'Modulos/notificacoes/PushNotificationController@index');
$router->get('/admin/notificacoes-push/criar', 'Modulos/notificacoes/PushNotificationController@create');
$router->post('/admin/notificacoes-push/enviar', 'Modulos/notificacoes/PushNotificationController@enviar');
$router->get('/admin/notificacoes-push/{id}', 'Modulos/notificacoes/PushNotificationController@show');
$router->get('/admin/notifications', 'Modulos/notificacoes/NotificationController@index');
$router->get('/admin/notifications/create', 'Modulos/notificacoes/NotificationController@create');
$router->post('/admin/notifications/store', 'Modulos/notificacoes/NotificationController@store');
$router->get('/admin/notifications/{id}', 'Modulos/notificacoes/NotificationController@show');
$router->get('/admin/notifications/{id}/edit', 'Modulos/notificacoes/NotificationController@edit');
$router->post('/admin/notifications/{id}/update', 'Modulos/notificacoes/NotificationController@update');
$router->get('/admin/notifications/{id}/delete', 'Modulos/notificacoes/NotificationController@delete');
$router->get('/admin/notifications/api/nao-lidas', 'Modulos/notificacoes/NotificationController@apiNaoLidas');
$router->post('/admin/notifications/api/marcar-lida', 'Modulos/notificacoes/NotificationController@apiMarcarLida');
$router->get('/api/notificacoes', 'Modulos/notificacoes/NotificacoesApiController@index');
$router->post('/api/notificacoes', 'Modulos/notificacoes/NotificacoesApiController@store');
