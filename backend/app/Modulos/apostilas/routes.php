<?php
/**
 * Rotas do módulo apostilas.
 *
 * @var Router $router
 */

$router->get('/admin/apostilas', 'Modulos/apostilas/AdminApostilaController@index');
$router->post('/admin/apostilas/upload', 'Modulos/apostilas/AdminApostilaController@upload');
$router->post('/admin/apostilas/editar', 'Modulos/apostilas/AdminApostilaController@update');
$router->post('/admin/apostilas/excluir', 'Modulos/apostilas/AdminApostilaController@delete');
$router->get('/admin/apostilas-ia', 'Modulos/apostilas/ApostilaIaAdminController@index');
$router->get('/admin/apostilas-ia/criar', 'Modulos/apostilas/ApostilaIaAdminController@criar');
$router->post('/admin/apostilas-ia/upload', 'Modulos/apostilas/ApostilaIaAdminController@upload');
$router->get('/admin/apostilas-ia/{id}/editar', 'Modulos/apostilas/ApostilaIaAdminController@editar');
$router->post('/admin/apostilas-ia/{id}/atualizar', 'Modulos/apostilas/ApostilaIaAdminController@atualizar');
$router->post('/admin/apostilas-ia/{id}/reprocessar', 'Modulos/apostilas/ApostilaIaAdminController@reprocessar');
$router->post('/admin/apostilas-ia/{id}/enviar-pdf', 'Modulos/apostilas/ApostilaIaAdminController@enviarPdf');
$router->post('/admin/apostilas-ia/{id}/enviar-capa', 'Modulos/apostilas/ApostilaIaAdminController@enviarCapa');
$router->get('/admin/apostilas-ia/{id}/capa', 'Modulos/apostilas/ApostilaIaAdminController@capa');
$router->get('/admin/apostilas-ia/{id}/pdf', 'Modulos/apostilas/ApostilaIaAdminController@pdf');
$router->get('/admin/apostilas-ia/{id}/status', 'Modulos/apostilas/ApostilaIaAdminController@status');
$router->get('/professor/apostilas', 'Modulos/apostilas/TeacherApostilaController@index');
$router->get('/professor/apostilas/abrir/{id}', 'Modulos/apostilas/TeacherApostilaController@abrir');
$router->get('/professor/apostilas/visualizar/{id}', 'Modulos/apostilas/TeacherApostilaController@visualizar');
$router->get('/professor/apostilas-ia', 'Modulos/apostilas/TeacherApostilaIaController@index');
$router->get('/professor/apostilas-ia-disponiveis', 'Modulos/apostilas/TeacherApostilaIaController@apostilasDisponiveisJson');
$router->get('/professor/apostilas-ia/{id}', 'Modulos/apostilas/TeacherApostilaIaController@abrir');
$router->post('/professor/apostilas-ia/{id}/chat', 'Modulos/apostilas/TeacherApostilaIaController@chat');
$router->post('/professor/apostilas-ia/{id}/chat-stream', 'Modulos/apostilas/TeacherApostilaIaController@chatStream');
$router->post('/professor/apostilas-ia/{id}/sessao/nova', 'Modulos/apostilas/TeacherApostilaIaController@novaSessao');
$router->get('/professor/apostilas-ia/{id}/exercicios', 'Modulos/apostilas/TeacherApostilaIaController@exercicios');
$router->post('/professor/apostilas-ia/{id}/gerar-prova', 'Modulos/apostilas/TeacherApostilaIaController@gerarProva');
$router->post('/professor/apostilas-ia/{id}/preparar-slides', 'Modulos/apostilas/TeacherApostilaIaController@prepararSlides');
$router->get('/professor/apostilas-ia/{id}/pagina/{numeroPagina}/imagem', 'Modulos/apostilas/TeacherApostilaIaController@imagemPagina');
$router->get('/professor/apostilas-ia/{id}/capa', 'Modulos/apostilas/TeacherApostilaIaController@capa');
$router->get('/professor/apostilas-ia/{id}/pdf', 'Modulos/apostilas/TeacherApostilaIaController@pdf');
$router->get('/aluno/apostilas', 'Modulos/apostilas/StudentApostilaController@index');
$router->get('/aluno/apostilas/abrir/{id}', 'Modulos/apostilas/StudentApostilaController@abrir');
$router->get('/aluno/apostilas/visualizar/{id}', 'Modulos/apostilas/StudentApostilaController@visualizar');
$router->get('/aluno/apostilas-ia', 'User/StudentApostilaIaController@index');
$router->get('/aluno/apostilas-ia/{id}', 'User/StudentApostilaIaController@abrir');
$router->post('/aluno/apostilas-ia/{id}/chat', 'User/StudentApostilaIaController@chat');
$router->post('/aluno/apostilas-ia/{id}/chat-stream', 'User/StudentApostilaIaController@chatStream');
$router->post('/aluno/apostilas-ia/{id}/sessao/nova', 'User/StudentApostilaIaController@novaSessao');
$router->get('/aluno/apostilas-ia/{id}/capa', 'User/StudentApostilaIaController@capa');
$router->get('/aluno/apostilas-ia/{id}/pdf', 'User/StudentApostilaIaController@pdf');
$router->get('/aluno/apostilas-ia/{id}/pagina/{numeroPagina}/imagem', 'User/StudentApostilaIaController@imagemPagina');
