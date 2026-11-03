<?php
/**
 * Rotas autenticadas da carteirinha.
 * A validação pública fica em config/routes/public.php.
 *
 * @var Router $router
 */

$router->get('/admin/carteirinha', 'Modulos/carteirinha/CarteirinhaAdminController@index');
$router->post('/admin/carteirinha/uso', 'Modulos/carteirinha/CarteirinhaAdminController@salvarUso');
$router->post('/admin/carteirinha/emitir', 'Modulos/carteirinha/CarteirinhaAdminController@emitir');
$router->post('/admin/carteirinha/emitir-turma', 'Modulos/carteirinha/CarteirinhaAdminController@emitirTurma');
$router->post('/admin/carteirinha/cancelar', 'Modulos/carteirinha/CarteirinhaAdminController@cancelar');
$router->get('/admin/carteirinha/imprimir', 'Modulos/carteirinha/CarteirinhaAdminController@imprimir');
$router->get('/admin/carteirinha/pdf', 'Modulos/carteirinha/CarteirinhaAdminController@pdf');

$router->get('/aluno/carteirinha', 'Modulos/carteirinha/CarteirinhaAlunoController@index');
$router->get('/aluno/carteirinha/imprimir', 'Modulos/carteirinha/CarteirinhaAlunoController@imprimir');
$router->get('/aluno/carteirinha/pdf', 'Modulos/carteirinha/CarteirinhaAlunoController@pdf');
