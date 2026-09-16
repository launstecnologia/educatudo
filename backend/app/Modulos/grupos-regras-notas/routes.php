<?php
/**
 * Rotas admin do módulo Quadro de Notas.
 *
 * @var Router $router
 */

$router->get('/admin/grupos-regras-notas', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@index');
$router->get('/admin/quadros-notas', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@index');
$router->get('/admin/grupos-regras-notas/novo', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@novo');
$router->get('/admin/quadros-notas/novo', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@novo');
$router->post('/admin/grupos-regras-notas', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@salvar');
$router->post('/admin/quadros-notas', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@salvar');
$router->get('/admin/grupos-regras-notas/{id}/dados', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@dadosJson');
$router->get('/admin/quadros-notas/{id}/dados', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@dadosJson');
$router->get('/admin/grupos-regras-notas/{id}/proxima-coluna', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@proximaColunaJson');
$router->get('/admin/quadros-notas/{id}/proxima-coluna', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@proximaColunaJson');
$router->get('/admin/grupos-regras-notas/{id}/editar', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@editar');
$router->get('/admin/quadros-notas/{id}/editar', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@editar');
$router->post('/admin/grupos-regras-notas/{id}/update', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@atualizar');
$router->post('/admin/quadros-notas/{id}/update', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@atualizar');
$router->post('/admin/grupos-regras-notas/{id}/delete', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@excluir');
$router->post('/admin/quadros-notas/{id}/delete', 'Modulos/grupos-regras-notas/GrupoRegrasNotasAdminController@excluir');
