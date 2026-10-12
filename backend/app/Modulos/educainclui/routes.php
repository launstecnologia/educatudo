<?php
/**
 * Rotas admin do módulo EducaInclui.
 *
 * @var Router $router
 */

$router->get('/admin/inclusao', 'Modulos/educainclui/EducaIncluiController@index');
$router->post('/admin/inclusao/salvar', 'Modulos/educainclui/EducaIncluiController@salvar');
$router->post('/admin/inclusao/ativar', 'Modulos/educainclui/EducaIncluiController@ativar');
$router->post('/admin/inclusao/aprovar-reforcada', 'Modulos/educainclui/EducaIncluiController@aprovarReforcada');
$router->post('/admin/inclusao/analisar-laudo', 'Modulos/educainclui/EducaIncluiController@analisarLaudo');
$router->get('/admin/inclusao/versoes', 'Modulos/educainclui/EducaIncluiController@versoes');
$router->post('/admin/inclusao/versoes/gerar', 'Modulos/educainclui/EducaIncluiController@gerarPendentes');
$router->get('/admin/inclusao/versoes/{id}/diff', 'Modulos/educainclui/EducaIncluiController@verVersao');
$router->get('/admin/inclusao/versoes/{id}/pdf', 'Modulos/educainclui/EducaIncluiController@pdfVersao');
$router->post('/admin/inclusao/versoes/aprovar', 'Modulos/educainclui/EducaIncluiController@aprovarVersao');
$router->post('/admin/inclusao/versoes/questao/refazer', 'Modulos/educainclui/EducaIncluiController@refazerQuestaoVersao');
$router->post('/admin/inclusao/versoes/questao/editar', 'Modulos/educainclui/EducaIncluiController@editarQuestaoVersao');
$router->post('/admin/inclusao/status', 'Modulos/educainclui/EducaIncluiController@status');
$router->post('/admin/inclusao/laudo/upload', 'Modulos/educainclui/EducaIncluiController@uploadLaudo');
$router->get('/admin/inclusao/laudo/{id}', 'Modulos/educainclui/EducaIncluiController@laudo');
$router->get('/admin/inclusao/aluno/{id}', 'Modulos/educainclui/EducaIncluiController@manage');
$router->get('/admin/inclusao/aluno/{id}/resumo', 'Modulos/educainclui/EducaIncluiController@resumoJson');
