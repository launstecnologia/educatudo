<?php
/**
 * Rotas admin do cadastro de Modelo de Boletim.
 *
 * @var Router $router
 */

$router->get('/admin/boletins', 'Modulos/boletins/BoletimCadastroAdminController@index');
$router->get('/admin/boletins/novo', 'Modulos/boletins/BoletimCadastroAdminController@novo');
$router->post('/admin/boletins', 'Modulos/boletins/BoletimCadastroAdminController@salvar');
$router->get('/admin/boletins/{id}/editar', 'Modulos/boletins/BoletimCadastroAdminController@editar');
$router->post('/admin/boletins/{id}/update', 'Modulos/boletins/BoletimCadastroAdminController@atualizar');
$router->post('/admin/boletins/{id}/delete', 'Modulos/boletins/BoletimCadastroAdminController@excluir');
$router->get('/admin/boletins/{id}/gerar-avaliacoes', 'Modulos/boletins/BoletimCadastroAdminController@gerarAvaliacoes');
$router->post('/admin/boletins/{id}/gerar-avaliacoes', 'Modulos/boletins/BoletimCadastroAdminController@gerarAvaliacoesExecutar');
$router->get('/admin/boletins/{id}/gerar-boletins', 'Modulos/boletins/BoletimCadastroAdminController@gerarBoletinsPagina');
$router->get('/admin/boletins/{id}/simular', 'Modulos/boletins/BoletimCadastroAdminController@simularBoletim');
$router->post('/admin/boletins/{id}/gerar', 'Modulos/boletins/BoletimCadastroAdminController@gerarDocumento');
