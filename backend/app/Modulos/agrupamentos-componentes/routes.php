<?php
/**
 * Rotas admin do módulo Agrupamento de Disciplinas.
 *
 * @var Router $router
 */

$router->get('/admin/agrupamentos-componentes', 'Modulos/agrupamentos-componentes/AgrupamentoComponenteAdminController@index');
$router->get('/admin/agrupamentos-componentes/novo', 'Modulos/agrupamentos-componentes/AgrupamentoComponenteAdminController@novo');
$router->post('/admin/agrupamentos-componentes', 'Modulos/agrupamentos-componentes/AgrupamentoComponenteAdminController@salvar');
$router->get('/admin/agrupamentos-componentes/{id}/dados', 'Modulos/agrupamentos-componentes/AgrupamentoComponenteAdminController@dadosJson');
$router->get('/admin/agrupamentos-componentes/{id}/editar', 'Modulos/agrupamentos-componentes/AgrupamentoComponenteAdminController@editar');
$router->post('/admin/agrupamentos-componentes/{id}/update', 'Modulos/agrupamentos-componentes/AgrupamentoComponenteAdminController@atualizar');
$router->post('/admin/agrupamentos-componentes/{id}/delete', 'Modulos/agrupamentos-componentes/AgrupamentoComponenteAdminController@excluir');
