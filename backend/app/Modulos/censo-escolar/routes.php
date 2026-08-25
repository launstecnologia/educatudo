<?php
/**
 * Rotas autenticadas do módulo Censo Escolar.
 *
 * @var Router $router
 */

$router->get('/admin/censo', 'Modulos/censo-escolar/CensoAdminController@index');
$router->post('/admin/censo/edicao', 'Modulos/censo-escolar/CensoAdminController@salvarEdicao');
$router->get('/admin/censo/{id}/config', 'Modulos/censo-escolar/CensoAdminController@config');
$router->post('/admin/censo/{id}/config', 'Modulos/censo-escolar/CensoAdminController@salvarConfig');
$router->post('/admin/censo/{id}/sincronizar', 'Modulos/censo-escolar/CensoAdminController@sincronizar');
$router->post('/admin/censo/{id}/validar', 'Modulos/censo-escolar/CensoAdminController@validar');
$router->get('/admin/censo/{id}/pendencias', 'Modulos/censo-escolar/CensoAdminController@pendencias');
$router->post('/admin/censo/{id}/pendencias/{pid}/conferir', 'Modulos/censo-escolar/CensoAdminController@conferirPendencia');
$router->get('/admin/censo/{id}/situacao', 'Modulos/censo-escolar/CensoAdminController@situacao');
$router->post('/admin/censo/{id}/situacao/{sid}', 'Modulos/censo-escolar/CensoAdminController@salvarSituacao');
$router->get('/admin/censo/{id}/previa', 'Modulos/censo-escolar/CensoAdminController@previa');
$router->get('/admin/censo/{id}/exportacoes', 'Modulos/censo-escolar/CensoAdminController@exportacoes');
$router->post('/admin/censo/{id}/exportacoes', 'Modulos/censo-escolar/CensoAdminController@gerarTxt');
$router->get('/admin/censo/{id}/exportacoes/{eid}/download', 'Modulos/censo-escolar/CensoAdminController@download');
$router->get('/admin/censo/{id}/retornos', 'Modulos/censo-escolar/CensoAdminController@retornos');
$router->post('/admin/censo/{id}/retornos', 'Modulos/censo-escolar/CensoAdminController@importarRetorno');
$router->post('/admin/censo/{id}/fechar', 'Modulos/censo-escolar/CensoAdminController@fechar');
$router->post('/admin/censo/{id}/reabrir', 'Modulos/censo-escolar/CensoAdminController@reabrir');
$router->get('/admin/censo/{id}/{entidade}', 'Modulos/censo-escolar/CensoAdminController@listagem');
$router->get('/admin/censo/{id}/{entidade}/{rid}', 'Modulos/censo-escolar/CensoAdminController@formulario');
$router->post('/admin/censo/{id}/{entidade}/{rid}', 'Modulos/censo-escolar/CensoAdminController@salvarFormulario');
