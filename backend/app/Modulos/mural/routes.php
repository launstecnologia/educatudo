<?php
/**
 * Rotas do módulo mural.
 *
 * @var Router $router
 */

// Admin
$router->get('/admin/mural-recados', 'Modulos/mural/AdminMuralController@index');
$router->get('/admin/mural-recados/criar', 'Modulos/mural/AdminMuralController@criar');
$router->post('/admin/mural-recados/salvar', 'Modulos/mural/AdminMuralController@salvar');
$router->get('/admin/mural-recados/{id}/dados', 'Modulos/mural/AdminMuralController@dados');
$router->get('/admin/mural-recados/editar', 'Modulos/mural/AdminMuralController@editar');
$router->post('/admin/mural-recados/atualizar', 'Modulos/mural/AdminMuralController@atualizar');
$router->post('/admin/mural-recados/excluir', 'Modulos/mural/AdminMuralController@excluir');

// Professor
$router->get('/professor/mural-recados', 'Modulos/mural/TeacherMuralController@index');
$router->get('/professor/mural-recados/criar', 'Modulos/mural/TeacherMuralController@criar');
$router->post('/professor/mural-recados/salvar', 'Modulos/mural/TeacherMuralController@salvar');
$router->get('/professor/mural-recados/editar', 'Modulos/mural/TeacherMuralController@editar');
$router->post('/professor/mural-recados/atualizar', 'Modulos/mural/TeacherMuralController@atualizar');
$router->post('/professor/mural-recados/excluir', 'Modulos/mural/TeacherMuralController@excluir');
