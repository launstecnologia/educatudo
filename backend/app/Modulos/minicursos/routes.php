<?php
/**
 * Rotas do módulo minicursos.
 *
 * @var Router $router
 */

$router->get('/admin/minicursos', 'Modulos/minicursos/AdminMinicursoController@index');
$router->get('/admin/minicursos/criar', 'Modulos/minicursos/AdminMinicursoController@create');
$router->post('/admin/minicursos/salvar', 'Modulos/minicursos/AdminMinicursoController@store');
$router->get('/admin/minicursos/{id}', 'Modulos/minicursos/AdminMinicursoController@show');
$router->get('/admin/minicursos/editar/{id}', 'Modulos/minicursos/AdminMinicursoController@edit');
$router->post('/admin/minicursos/atualizar/{id}', 'Modulos/minicursos/AdminMinicursoController@update');
$router->post('/admin/minicursos/excluir/{id}', 'Modulos/minicursos/AdminMinicursoController@delete');
$router->post('/admin/minicursos/modulo/salvar', 'Modulos/minicursos/AdminMinicursoController@moduloStore');
$router->post('/admin/minicursos/modulo/excluir', 'Modulos/minicursos/AdminMinicursoController@moduloDelete');
$router->post('/admin/minicursos/aula/salvar', 'Modulos/minicursos/AdminMinicursoController@aulaStore');
$router->post('/admin/minicursos/aula/atualizar', 'Modulos/minicursos/AdminMinicursoController@aulaUpdate');
$router->post('/admin/minicursos/aula/excluir', 'Modulos/minicursos/AdminMinicursoController@aulaDelete');
$router->get('/minicursos', 'Modulos/minicursos/StudentMinicursoController@index');
$router->get('/minicursos/aula/{id}', 'Modulos/minicursos/StudentMinicursoController@aula');
$router->post('/minicursos/marcar-vista', 'Modulos/minicursos/StudentMinicursoController@marcarVista');
$router->get('/minicursos/{id}', 'Modulos/minicursos/StudentMinicursoController@show');
