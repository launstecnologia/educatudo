<?php

/**
 * Rotas do leitor de gabarito.
 * Handler: Modulos/leitor-gabarito/<Controller>@method
 *
 * @var Router $router
 */

$router->get('/admin/leitor-gabarito', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@index');
$router->get('/admin/leitor-gabarito/blocos/{id}', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@bloco');
$router->post('/admin/leitor-gabarito/blocos/{id}/gerar', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@gerarBloco');
$router->post('/admin/leitor-gabarito/blocos/{id}/enviar', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@enviarBloco');
$router->post('/admin/leitor-gabarito/blocos/{id}/processar', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@processarBloco');
$router->get('/admin/leitor-gabarito/provas/{id}', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@prova');
$router->post('/admin/leitor-gabarito/provas/{id}/gerar', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@gerar');
$router->get('/admin/leitor-gabarito/provas/{id}/imprimir', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@imprimir');
$router->post('/admin/leitor-gabarito/provas/{id}/enviar', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@enviar');
$router->post('/admin/leitor-gabarito/provas/{id}/processar', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@processar');
$router->get('/admin/leitor-gabarito/folhas/{id}/png', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@pngFolha');
$router->get('/admin/leitor-gabarito/leituras/{id}', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@revisao');
$router->get('/admin/leitor-gabarito/leituras/{id}/imagem', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@imagem');
$router->post('/admin/leitor-gabarito/leituras/{id}/confirmar', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@confirmar');
$router->post('/admin/leitor-gabarito/leituras/{id}/reler', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@reler');
$router->post('/admin/leitor-gabarito/leituras/{id}/excluir', 'Modulos/leitor-gabarito/LeitorGabaritoAdminController@excluir');

$router->get('/professor/leitor-gabarito', 'Modulos/leitor-gabarito/LeitorGabaritoProfessorController@index');
$router->get('/professor/leitor-gabarito/provas/{id}', 'Modulos/leitor-gabarito/LeitorGabaritoProfessorController@prova');
$router->post('/professor/leitor-gabarito/provas/{id}/gerar', 'Modulos/leitor-gabarito/LeitorGabaritoProfessorController@gerar');
$router->get('/professor/leitor-gabarito/provas/{id}/imprimir', 'Modulos/leitor-gabarito/LeitorGabaritoProfessorController@imprimir');
$router->post('/professor/leitor-gabarito/provas/{id}/enviar', 'Modulos/leitor-gabarito/LeitorGabaritoProfessorController@enviar');
$router->post('/professor/leitor-gabarito/provas/{id}/processar', 'Modulos/leitor-gabarito/LeitorGabaritoProfessorController@processar');
$router->get('/professor/leitor-gabarito/folhas/{id}/png', 'Modulos/leitor-gabarito/LeitorGabaritoProfessorController@pngFolha');
$router->get('/professor/leitor-gabarito/leituras/{id}', 'Modulos/leitor-gabarito/LeitorGabaritoProfessorController@revisao');
$router->get('/professor/leitor-gabarito/leituras/{id}/imagem', 'Modulos/leitor-gabarito/LeitorGabaritoProfessorController@imagem');
$router->post('/professor/leitor-gabarito/leituras/{id}/confirmar', 'Modulos/leitor-gabarito/LeitorGabaritoProfessorController@confirmar');
$router->post('/professor/leitor-gabarito/leituras/{id}/reler', 'Modulos/leitor-gabarito/LeitorGabaritoProfessorController@reler');
$router->post('/professor/leitor-gabarito/leituras/{id}/excluir', 'Modulos/leitor-gabarito/LeitorGabaritoProfessorController@excluir');
