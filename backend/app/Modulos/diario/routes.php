<?php
/**
 * Rotas autenticadas do módulo Diário de Classe.
 *
 * @var Router $router
 */

$router->get('/professor/diario', 'Modulos/diario/DiarioProfessorController@index');
$router->get('/professor/diario/abrir', 'Modulos/diario/DiarioProfessorController@abrir');
$router->post('/professor/diario/salvar', 'Modulos/diario/DiarioProfessorController@salvar');
$router->get('/professor/diarios', 'Modulos/diario/DiarioProfessorController@listar');
$router->get('/professor/diarios/abrir', 'Modulos/diario/DiarioProfessorController@abrirDiario');
$router->post('/professor/diarios/fechar', 'Modulos/diario/DiarioProfessorController@fecharPeriodo');
$router->post('/professor/diarios/vincular-plano', 'Modulos/diario/DiarioProfessorController@vincularPlano');

$router->get('/admin/diario', 'Modulos/diario/DiarioAdminController@index');
$router->get('/admin/diario/indicadores', 'Modulos/diario/DiarioAdminController@indicadores');
$router->get('/admin/diario/aula', 'Modulos/diario/DiarioAdminController@aula');
$router->get('/admin/diario/lancar', 'Modulos/diario/DiarioAdminController@lancar');
$router->post('/admin/diario/salvar', 'Modulos/diario/DiarioAdminController@salvar');
$router->get('/admin/diario/relatorio/pdf', 'Modulos/diario/DiarioAdminController@relatorioPdf');
$router->get('/admin/diario/relatorio/excel', 'Modulos/diario/DiarioAdminController@relatorioExcel');
$router->get('/admin/diario/fechado', 'Modulos/diario/DiarioAdminController@fechado');
$router->post('/admin/diario/reabrir', 'Modulos/diario/DiarioAdminController@reabrirPeriodo');
