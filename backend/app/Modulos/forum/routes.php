<?php
/**
 * Rotas do módulo forum.
 *
 * @var Router $router
 */

$router->get('/forum', 'Modulos/forum/ForumController@index');
$router->get('/forum/create', 'Modulos/forum/ForumController@create');
$router->post('/forum/store', 'Modulos/forum/ForumController@store');
$router->get('/forum/{id}', 'Modulos/forum/ForumController@show');
$router->post('/forum/reply', 'Modulos/forum/ForumController@reply');
$router->post('/forum/vote', 'Modulos/forum/ForumController@vote');
$router->post('/forum/mark-best', 'Modulos/forum/ForumController@markBestAnswer');
$router->get('/forum/moderation/reports', 'Modulos/forum/ForumModerationController@reports');
$router->get('/forum/moderation/alerts', 'Modulos/forum/ForumModerationController@alerts');
$router->post('/forum/moderation/alerts/mark-seen', 'Modulos/forum/ForumModerationController@markAlertSeen');
$router->post('/forum/moderation/resolve-report', 'Modulos/forum/ForumModerationController@resolveReport');
$router->post('/forum/moderation/delete-topic', 'Modulos/forum/ForumModerationController@deleteTopic');
$router->post('/forum/moderation/delete-reply', 'Modulos/forum/ForumModerationController@deleteReply');
