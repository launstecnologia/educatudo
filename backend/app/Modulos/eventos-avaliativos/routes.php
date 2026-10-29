<?php
/**
 * Rotas admin do módulo Evento Avaliativo.
 *
 * @var Router $router
 */

$router->get('/admin/eventos-avaliativos', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@index');
$router->get('/admin/eventos-avaliativos/boletim', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@boletim');
$router->get('/admin/eventos-avaliativos/boletim/novo', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@novoBoletim');
$router->post('/admin/eventos-avaliativos/boletim/novo', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@criarBoletim');
$router->get('/admin/eventos-avaliativos/boletim/{id}/dados', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@dadosBoletim');
$router->post('/admin/eventos-avaliativos/boletim/{id}/dados', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@atualizarBoletim');
$router->post('/admin/eventos-avaliativos/boletim/{id}/excluir', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@excluirBoletim');
$router->get('/admin/eventos-avaliativos/boletim/{id}', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@layoutBoletim');
$router->post('/admin/eventos-avaliativos/boletim/{id}', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@salvarBoletim');
$router->get('/admin/eventos-avaliativos/criar', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@criar');
$router->get('/admin/eventos-avaliativos/alunos-recuperacao', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@alunosRecuperacao');
$router->post('/admin/eventos-avaliativos', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@salvar');
$router->post('/admin/eventos-avaliativos/duplicar', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@duplicar');
$router->post('/admin/eventos-avaliativos/{id}/publicar', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@publicar');
$router->get('/admin/eventos-avaliativos/{id}/editar', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@editar');
$router->post('/admin/eventos-avaliativos/{id}', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@atualizar');
$router->get('/admin/eventos-avaliativos/{id}', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@detalhe');
$router->post('/admin/eventos-avaliativos/{id}/excluir', 'Modulos/eventos-avaliativos/EventoAvaliativoAdminController@excluir');
