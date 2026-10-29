<?php
/**
 * Rotas admin do módulo Regras de Aprovação.
 *
 * @var Router $router
 */

$router->get('/admin/regras-academicas/recuperacao/lista', 'Modulos/regras-academicas/RecuperacaoAdminController@lista');
$router->get('/admin/regras-academicas/recuperacao', 'Modulos/regras-academicas/RecuperacaoAdminController@index');
$router->post('/admin/regras-academicas/recuperacao', 'Modulos/regras-academicas/RecuperacaoAdminController@salvar');
$router->get('/admin/regras-academicas/recuperacao/{id}/dados', 'Modulos/regras-academicas/RecuperacaoAdminController@dados');
$router->post('/admin/regras-academicas/recuperacao/{id}/excluir', 'Modulos/regras-academicas/RecuperacaoAdminController@excluir');
$router->post('/admin/regras-academicas/recuperacao/{id}', 'Modulos/regras-academicas/RecuperacaoAdminController@atualizar');
$router->get('/admin/regras-academicas', 'Modulos/regras-academicas/RegraAcademicaAdminController@index');
$router->get('/admin/regras-academicas/nova', 'Modulos/regras-academicas/RegraAcademicaAdminController@nova');
$router->post('/admin/regras-academicas', 'Modulos/regras-academicas/RegraAcademicaAdminController@salvar');
$router->get('/admin/regras-academicas/{id}/editar', 'Modulos/regras-academicas/RegraAcademicaAdminController@editar');
$router->post('/admin/regras-academicas/{id}/update', 'Modulos/regras-academicas/RegraAcademicaAdminController@atualizar');
$router->post('/admin/regras-academicas/{id}/delete', 'Modulos/regras-academicas/RegraAcademicaAdminController@excluir');
