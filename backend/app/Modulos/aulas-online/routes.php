<?php
/**
 * Rotas do módulo aulas-online.
 *
 * @var Router $router
 */

$router->get('/admin/aulas-online', 'Modulos/aulas-online/OnlineClassController@index');
$router->get('/admin/aulas-online/criar', 'Modulos/aulas-online/OnlineClassController@criar');
$router->get('/admin/aulas-online/editar', 'Modulos/aulas-online/OnlineClassController@editar');
$router->get('/admin/aulas-online/chat', 'Modulos/aulas-online/OnlineClassController@chat');
$router->get('/admin/aulas-online/chat/mensagens', 'Modulos/aulas-online/OnlineClassController@chatMensagens');
$router->post('/admin/aulas-online/chat/enviar', 'Modulos/aulas-online/OnlineClassController@chatEnviar');
$router->post('/admin/aulas-online/salvar', 'Modulos/aulas-online/OnlineClassController@salvar');
$router->post('/admin/aulas-online/atualizar', 'Modulos/aulas-online/OnlineClassController@atualizar');
$router->post('/admin/aulas-online/retry-integracao', 'Modulos/aulas-online/OnlineClassController@retryIntegracao');
$router->post('/admin/aulas-online/sincronizar-gravacoes', 'Modulos/aulas-online/OnlineClassController@sincronizarGravacoes');
$router->post('/admin/aulas-online/excluir', 'Modulos/aulas-online/OnlineClassController@excluir');
$router->post('/admin/aulas-online/arquivo/upload', 'Modulos/aulas-online/OnlineClassController@uploadArquivo');
$router->post('/admin/aulas-online/arquivo/excluir', 'Modulos/aulas-online/OnlineClassController@excluirArquivo');
$router->get('/admin/aulas-online/arquivo/download', 'Modulos/aulas-online/OnlineClassController@downloadArquivo');
