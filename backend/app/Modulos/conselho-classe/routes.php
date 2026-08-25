<?php
/**
 * Rotas autenticadas do módulo Conselho de Classe.
 *
 * @var Router $router
 */

$router->get('/admin/conselhos', 'Modulos/conselho-classe/ConselhoAdminController@index');
$router->get('/admin/conselhos/novo', 'Modulos/conselho-classe/ConselhoAdminController@nova');
$router->post('/admin/conselhos', 'Modulos/conselho-classe/ConselhoAdminController@salvar');
$router->get('/admin/conselhos/{id}', 'Modulos/conselho-classe/ConselhoAdminController@show');
$router->get('/admin/conselhos/{id}/aluno/{alunoId}', 'Modulos/conselho-classe/ConselhoAdminController@aluno');
$router->post('/admin/conselhos/{id}/abrir', 'Modulos/conselho-classe/ConselhoAdminController@abrir');
$router->post('/admin/conselhos/{id}/finalizar', 'Modulos/conselho-classe/ConselhoAdminController@finalizar');
$router->post('/admin/conselhos/{id}/reabrir', 'Modulos/conselho-classe/ConselhoAdminController@reabrir');
$router->post('/admin/conselhos/{id}/participantes', 'Modulos/conselho-classe/ConselhoAdminController@participantes');
$router->post('/admin/conselhos/{id}/deliberar', 'Modulos/conselho-classe/ConselhoAdminController@deliberar');
$router->post('/admin/conselhos/{id}/encaminhar', 'Modulos/conselho-classe/ConselhoAdminController@encaminhar');
$router->get('/admin/conselhos/{id}/ata', 'Modulos/conselho-classe/ConselhoAdminController@ata');
$router->post('/admin/conselhos/{id}/ata', 'Modulos/conselho-classe/ConselhoAdminController@salvarAta');
$router->get('/admin/conselhos/{id}/ata/pdf', 'Modulos/conselho-classe/ConselhoAdminController@ataPdf');

$router->get('/professor/conselhos', 'Modulos/conselho-classe/ConselhoProfessorController@index');
$router->get('/professor/conselhos/{id}', 'Modulos/conselho-classe/ConselhoProfessorController@show');
$router->post('/professor/conselhos/{id}/observacao', 'Modulos/conselho-classe/ConselhoProfessorController@observacao');
