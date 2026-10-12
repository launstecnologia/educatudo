<?php
/**
 * Rotas admin do módulo Modelos de Documentos.
 *
 * @var Router $router
 */

$router->get('/admin/modelos-documentos', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@index');
$router->get('/admin/paineis/documentos', 'Modulos/modelos-documentos/ArquivoEmissaoDocumentoAdminController@index');
$router->post('/admin/paineis/documentos/gerar', 'Modulos/modelos-documentos/ArquivoEmissaoDocumentoAdminController@gerar');
$router->get('/admin/paineis/documentos/{id}/pdf', 'Modulos/modelos-documentos/ArquivoEmissaoDocumentoAdminController@pdf');
$router->get('/admin/modelos-documentos/emitir-lote', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@emitirLote');
$router->get('/admin/modelos-documentos/emitir-lote/pdf', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@emitirLotePdf');
$router->get('/admin/modelos-documentos/importar', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@importar');
$router->post('/admin/modelos-documentos/importar', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@processarImportacao');
$router->post('/admin/modelos-documentos/importar/catalogo', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@aplicarCatalogo');
$router->get('/admin/modelos-documentos/importar/conferir', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@conferirImportacao');
$router->post('/admin/modelos-documentos/importar/conferir', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@confirmarImportacao');
$router->get('/admin/modelos-documentos/layout', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@layout');
$router->post('/admin/modelos-documentos/layout', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@salvarLayout');
$router->get('/admin/modelos-documentos/editor', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@editor');
$router->get('/admin/modelos-documentos/demonstracao', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@demonstracao');
$router->post('/admin/modelos-documentos/reproduzir-imagem', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@reproduzirImagem');
$router->post('/admin/modelos-documentos/estrutura', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@salvarEstruturaNovo');
$router->get('/admin/modelos-documentos/create', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@create');
$router->post('/admin/modelos-documentos', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@store');
$router->get('/admin/modelos-documentos/{id}/editor', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@editor');
$router->post('/admin/modelos-documentos/{id}/estrutura', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@salvarEstrutura');
$router->get('/admin/modelos-documentos/{id}/edit', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@edit');
$router->post('/admin/modelos-documentos/{id}/edit', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@update');
$router->get('/admin/modelos-documentos/{id}/preview', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@preview');
$router->post('/admin/modelos-documentos/excluir', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@destroy');
