<?php
/**
 * Rotas admin do fechamento oficial, livro de homologações e documentos do período.
 *
 * @var Router $router
 */

$router->get('/admin/fechamento', 'Modulos/fechamento/FechamentoAdminController@index');
$router->get('/admin/fechamento/impressao-lote', 'Modulos/fechamento/FechamentoAdminController@impressaoLote');
$router->post('/admin/fechamento/impressao-lote/enfileirar', 'Modulos/fechamento/FechamentoAdminController@enfileirarImpressao');
$router->get('/admin/fechamento/impressao-lote/arquivo/{id}', 'Modulos/fechamento/FechamentoAdminController@arquivoImpressao');
$router->post('/admin/fechamento/impressao-lote/preparar-historicos', 'Modulos/fechamento/FechamentoAdminController@prepararHistoricos');
$router->get('/admin/fechamento/turma/{id}/homologacoes/dados', 'Modulos/fechamento/FechamentoAdminController@dadosHomologacoes');
$router->get('/admin/fechamento/turma/{id}/documentos/dados', 'Modulos/fechamento/FechamentoAdminController@dadosDocumentos');
$router->get('/admin/fechamento/turma/{id}', 'Modulos/fechamento/FechamentoAdminController@turma');
$router->post('/admin/fechamento/turma/{id}/iniciar', 'Modulos/fechamento/FechamentoAdminController@iniciar');
$router->post('/admin/fechamento/turma/{id}/reabrir', 'Modulos/fechamento/FechamentoAdminController@reabrir');
$router->post('/admin/fechamento/turma/{id}/homologar', 'Modulos/fechamento/FechamentoAdminController@homologar');
$router->post('/admin/fechamento/turma/{id}/retificar', 'Modulos/fechamento/FechamentoAdminController@retificar');
$router->get('/admin/homologacoes', 'Modulos/fechamento/FechamentoAdminController@homologacoes');
$router->get('/admin/documentos-periodo', 'Modulos/fechamento/FechamentoAdminController@documentos');
$router->get('/admin/diagnostico-menu', 'Modulos/fechamento/FechamentoAdminController@diagnosticoMenu');
$router->get('/admin/implantar-academico', 'Modulos/fechamento/FechamentoAdminController@implantar');
