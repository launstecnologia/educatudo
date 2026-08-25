<?php
/**
 * Rotas admin do módulo Modelos de Documentos.
 *
 * @var Router $router
 */

$router->get('/admin/modelos-documentos', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@index');
$router->get('/admin/modelos-documentos/layout', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@layout');
$router->post('/admin/modelos-documentos/layout', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@salvarLayout');
$router->get('/admin/modelos-documentos/editor', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@editor');
$router->post('/admin/modelos-documentos/estrutura', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@salvarEstruturaNovo');
$router->get('/admin/modelos-documentos/create', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@create');
$router->post('/admin/modelos-documentos', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@store');
$router->get('/admin/modelos-documentos/{id}/editor', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@editor');
$router->post('/admin/modelos-documentos/{id}/estrutura', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@salvarEstrutura');
$router->get('/admin/modelos-documentos/{id}/edit', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@edit');
$router->post('/admin/modelos-documentos/{id}/edit', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@update');
$router->get('/admin/modelos-documentos/{id}/preview', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@preview');
$router->post('/admin/modelos-documentos/excluir', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@destroy');
