<?php
/**
 * Rotas admin do módulo Regras Acadêmicas.
 *
 * @var Router $router
 */

$router->get('/admin/regras-academicas', 'Modulos/regras-academicas/RegraAcademicaAdminController@index');
$router->get('/admin/regras-academicas/nova', 'Modulos/regras-academicas/RegraAcademicaAdminController@nova');
$router->post('/admin/regras-academicas', 'Modulos/regras-academicas/RegraAcademicaAdminController@salvar');
$router->get('/admin/regras-academicas/{id}/editar', 'Modulos/regras-academicas/RegraAcademicaAdminController@editar');
$router->post('/admin/regras-academicas/{id}/update', 'Modulos/regras-academicas/RegraAcademicaAdminController@atualizar');
$router->post('/admin/regras-academicas/{id}/delete', 'Modulos/regras-academicas/RegraAcademicaAdminController@excluir');
