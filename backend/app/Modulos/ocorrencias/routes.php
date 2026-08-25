<?php
/**
 * Rotas autenticadas do módulo Ocorrências do aluno.
 * IA/áudio legado permanece em OccurrenceAdminController (núcleo).
 *
 * @var Router $router
 */

$router->get('/admin/ocorrencias', 'Modulos/ocorrencias/OcorrenciaAdminController@index');
$router->get('/admin/ocorrencias/nova', 'Modulos/ocorrencias/OcorrenciaAdminController@nova');
$router->post('/admin/ocorrencias', 'Modulos/ocorrencias/OcorrenciaAdminController@salvar');
$router->post('/admin/ocorrencias/categorias', 'Modulos/ocorrencias/OcorrenciaAdminController@salvarCategoria');
$router->get('/admin/ocorrencias/buscar-alunos', 'Modulos/ocorrencias/OcorrenciaAdminController@buscarAlunos');
$router->get('/admin/ocorrencias/{id}', 'Modulos/ocorrencias/OcorrenciaAdminController@show');
$router->post('/admin/ocorrencias/{id}/status', 'Modulos/ocorrencias/OcorrenciaAdminController@atualizarStatus');
$router->post('/admin/ocorrencias/{id}/pais', 'Modulos/ocorrencias/OcorrenciaAdminController@atualizarPais');
$router->post('/admin/ocorrencias/{id}/encaminhamento', 'Modulos/ocorrencias/OcorrenciaAdminController@atualizarEncaminhamento');

$router->get('/professor/ocorrencias/nova', 'Modulos/ocorrencias/OcorrenciaProfessorController@nova');
$router->post('/professor/ocorrencias', 'Modulos/ocorrencias/OcorrenciaProfessorController@salvar');
