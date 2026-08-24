<?php
/**
 * Rotas do módulo Arquivos (admin, professor, aluno).
 * Handler: Modulos/arquivos/<Controller>@method
 *
 * @var Router $router
 */

// Admin
$router->get('/admin/arquivos', 'Modulos/arquivos/ArquivosAdminController@index');
$router->get('/admin/arquivos/criar', 'Modulos/arquivos/ArquivosAdminController@criar');
$router->get('/admin/arquivos/editar', 'Modulos/arquivos/ArquivosAdminController@editar');
$router->get('/admin/arquivos/baixar/{id}', 'Modulos/arquivos/ArquivosAdminController@baixarAnexo');
$router->post('/admin/arquivos/upload', 'Modulos/arquivos/ArquivosAdminController@upload');
$router->post('/admin/arquivos/editar', 'Modulos/arquivos/ArquivosAdminController@update');
$router->post('/admin/arquivos/excluir', 'Modulos/arquivos/ArquivosAdminController@delete');
$router->post('/admin/arquivos/pasta/criar', 'Modulos/arquivos/ArquivosAdminController@createFolder');
$router->post('/admin/arquivos/pasta/renomear', 'Modulos/arquivos/ArquivosAdminController@renameFolder');
$router->post('/admin/arquivos/pasta/excluir', 'Modulos/arquivos/ArquivosAdminController@deleteFolder');
$router->post('/admin/arquivos/pasta/mover', 'Modulos/arquivos/ArquivosAdminController@moveToFolder');

// Professor
$router->get('/professor/arquivos', 'Modulos/arquivos/ArquivosProfessorController@index');
$router->get('/professor/arquivos/criar', 'Modulos/arquivos/ArquivosProfessorController@create');
$router->get('/professor/arquivos/alunos-por-turma', 'Modulos/arquivos/ArquivosProfessorController@alunosPorTurma');
$router->post('/professor/arquivos/salvar', 'Modulos/arquivos/ArquivosProfessorController@store');
$router->get('/professor/arquivos/editar/{id}', 'Modulos/arquivos/ArquivosProfessorController@edit');
$router->get('/professor/arquivos/preview/{id}', 'Modulos/arquivos/ArquivosProfessorController@preview');
$router->get('/professor/arquivos/ver-anexo/{id}', 'Modulos/arquivos/ArquivosProfessorController@verAnexo');
$router->post('/professor/arquivos/atualizar', 'Modulos/arquivos/ArquivosProfessorController@update');
$router->post('/professor/arquivos/excluir/{id}', 'Modulos/arquivos/ArquivosProfessorController@delete');
$router->post('/professor/arquivos/pasta/criar', 'Modulos/arquivos/ArquivosProfessorController@createFolder');
$router->post('/professor/arquivos/pasta/renomear', 'Modulos/arquivos/ArquivosProfessorController@renameFolder');
$router->post('/professor/arquivos/pasta/excluir', 'Modulos/arquivos/ArquivosProfessorController@deleteFolder');
$router->post('/professor/arquivos/pasta/mover', 'Modulos/arquivos/ArquivosProfessorController@moveToFolder');

// Aluno
$router->get('/aluno/arquivos', 'Modulos/arquivos/ArquivosAlunoController@index');
$router->get('/aluno/arquivos/ver/{id}', 'Modulos/arquivos/ArquivosAlunoController@ver');
$router->get('/aluno/arquivos/abrir/{id}', 'Modulos/arquivos/ArquivosAlunoController@abrir');
$router->get('/aluno/arquivos/visualizar/{id}', 'Modulos/arquivos/ArquivosAlunoController@visualizarAnexo');
$router->get('/aluno/recuperacao', 'Modulos/arquivos/ArquivosAlunoController@recuperacao');

// Pais (somente leitura — mesmos materiais da turma do aluno)
$router->get('/pais/filhos/{id}/arquivos/ver/{pubId}', 'Modulos/arquivos/ArquivosPaisController@ver');
$router->get('/pais/filhos/{id}/arquivos/visualizar/{anexoId}', 'Modulos/arquivos/ArquivosPaisController@visualizarAnexo');
$router->get('/pais/filhos/{id}/arquivos', 'Modulos/arquivos/ArquivosPaisController@index');
