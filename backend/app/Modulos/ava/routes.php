<?php
/**
 * Rotas do módulo ava.
 *
 * @var Router $router
 */

$router->get('/admin/ava', 'Modulos/ava/AvaCourseAdminController@index');
$router->get('/admin/ava/categorias', 'Modulos/ava/AvaCourseAdminController@categorias');
$router->post('/admin/ava/categorias', 'Modulos/ava/AvaCourseAdminController@storeCategoria');
$router->post('/admin/ava/categorias/{id}/excluir', 'Modulos/ava/AvaCourseAdminController@deleteCategoria');
$router->get('/admin/ava/cursos/novo', 'Modulos/ava/AvaCourseAdminController@create');
$router->post('/admin/ava/cursos', 'Modulos/ava/AvaCourseAdminController@store');
$router->get('/admin/ava/cursos/{id}/editar', 'Modulos/ava/AvaCourseAdminController@edit');
$router->post('/admin/ava/cursos/{id}/excluir', 'Modulos/ava/AvaCourseAdminController@delete');
$router->get('/admin/ava/cursos/{id}/periodos', 'Modulos/ava/AvaCourseAdminController@periodos');
$router->post('/admin/ava/cursos/{id}/semestres', 'Modulos/ava/AvaCourseAdminController@storeSemestre');
$router->post('/admin/ava/cursos/{id}', 'Modulos/ava/AvaCourseAdminController@update');
$router->get('/admin/ava/cursos/{id}', 'Modulos/ava/AvaCourseAdminController@show');
$router->post('/admin/ava/semestres/{id}/excluir', 'Modulos/ava/AvaCourseAdminController@deleteSemestre');
$router->post('/admin/ava/disciplinas', 'Modulos/ava/AvaDisciplineAdminController@store');
$router->post('/admin/ava/disciplinas/{id}/sincronizar-turma', 'Modulos/ava/AvaDisciplineAdminController@syncTurma');
$router->post('/admin/ava/disciplinas/{id}/cancelar-matricula', 'Modulos/ava/AvaDisciplineAdminController@unenroll');
$router->post('/admin/ava/disciplinas/{id}/excluir', 'Modulos/ava/AvaDisciplineAdminController@delete');
$router->get('/admin/ava/disciplinas/{id}/editar', 'Modulos/ava/AvaDisciplineAdminController@edit');
$router->get('/admin/ava/disciplinas/{id}/avaliacoes', 'Modulos/ava/AvaDisciplineAdminController@avaliacoes');
$router->post('/admin/ava/disciplinas/{id}/avaliacoes', 'Modulos/ava/AvaDisciplineAdminController@storeAvaliacao');
$router->post('/admin/ava/avaliacoes/{id}/excluir', 'Modulos/ava/AvaDisciplineAdminController@deleteAvaliacao');
$router->post('/admin/ava/disciplinas/{id}', 'Modulos/ava/AvaDisciplineAdminController@update');
$router->get('/admin/ava/disciplinas/{id}', 'Modulos/ava/AvaDisciplineAdminController@show');
$router->post('/admin/ava/modulos', 'Modulos/ava/AvaContentAdminController@storeModulo');
$router->get('/admin/ava/modulos/{id}/aulas/nova', 'Modulos/ava/AvaContentAdminController@createAula');
$router->post('/admin/ava/modulos/{id}/aulas', 'Modulos/ava/AvaContentAdminController@storeAula');
$router->post('/admin/ava/modulos/{id}/excluir', 'Modulos/ava/AvaContentAdminController@deleteModulo');
$router->post('/admin/ava/modulos/{id}', 'Modulos/ava/AvaContentAdminController@updateModulo');
$router->get('/admin/ava/aulas/{id}/editar', 'Modulos/ava/AvaContentAdminController@editAula');
$router->post('/admin/ava/aulas/{id}/anexos', 'Modulos/ava/AvaContentAdminController@uploadAnexo');
$router->post('/admin/ava/aulas/{id}/excluir', 'Modulos/ava/AvaContentAdminController@deleteAula');
$router->post('/admin/ava/aulas/{id}', 'Modulos/ava/AvaContentAdminController@updateAula');
$router->post('/admin/ava/anexos/{id}/excluir', 'Modulos/ava/AvaContentAdminController@deleteAnexo');
$router->get('/professor/ava', 'Modulos/ava/AvaTeacherController@index');
$router->get('/professor/ava/disciplinas/{id}', 'Modulos/ava/AvaTeacherController@show');
$router->post('/professor/ava/modulos', 'Modulos/ava/AvaTeacherController@storeModulo');
$router->get('/professor/ava/modulos/{id}/aulas/nova', 'Modulos/ava/AvaTeacherController@createAula');
$router->post('/professor/ava/modulos/{id}/aulas', 'Modulos/ava/AvaTeacherController@storeAula');
$router->post('/professor/ava/modulos/{id}/excluir', 'Modulos/ava/AvaTeacherController@deleteModulo');
$router->post('/professor/ava/modulos/{id}', 'Modulos/ava/AvaTeacherController@updateModulo');
$router->get('/professor/ava/aulas/{id}/editar', 'Modulos/ava/AvaTeacherController@editAula');
$router->post('/professor/ava/aulas/{id}/anexos', 'Modulos/ava/AvaTeacherController@uploadAnexo');
$router->post('/professor/ava/aulas/{id}/excluir', 'Modulos/ava/AvaTeacherController@deleteAula');
$router->post('/professor/ava/aulas/{id}', 'Modulos/ava/AvaTeacherController@updateAula');
$router->post('/professor/ava/anexos/{id}/excluir', 'Modulos/ava/AvaTeacherController@deleteAnexo');
$router->post('/professor/ava/aulas/{id}/comentario', 'Modulos/ava/AvaCommentController@store');
$router->post('/professor/ava/comentario/{id}/excluir', 'Modulos/ava/AvaCommentController@delete');
$router->post('/professor/ava/comentario/{id}/fixar', 'Modulos/ava/AvaCommentController@pin');
$router->get('/professor/ava/disciplinas/{id}/ao-vivo/nova', 'Modulos/ava/AvaLiveTeacherController@create');
$router->get('/professor/ava/disciplinas/{id}/ao-vivo', 'Modulos/ava/AvaLiveTeacherController@list');
$router->post('/professor/ava/disciplinas/{id}/ao-vivo', 'Modulos/ava/AvaLiveTeacherController@store');
$router->get('/professor/ava/ao-vivo/{id}/editar', 'Modulos/ava/AvaLiveTeacherController@edit');
$router->get('/professor/ava/ao-vivo/{id}/entrar', 'Modulos/ava/AvaLiveTeacherController@enter');
$router->post('/professor/ava/ao-vivo/{id}/status', 'Modulos/ava/AvaLiveTeacherController@setStatus');
$router->post('/professor/ava/ao-vivo/{id}/gravacao', 'Modulos/ava/AvaLiveTeacherController@setRecording');
$router->post('/professor/ava/ao-vivo/{id}/excluir', 'Modulos/ava/AvaLiveTeacherController@delete');
$router->post('/professor/ava/ao-vivo/{id}', 'Modulos/ava/AvaLiveTeacherController@update');
$router->get('/cursos/disciplina/{id}/atividades', 'Modulos/ava/AvaActivityStudentController@list');
$router->get('/cursos/atividade/{id}', 'Modulos/ava/AvaActivityStudentController@show');
$router->post('/cursos/atividade/{id}/enviar', 'Modulos/ava/AvaActivityStudentController@submit');
$router->post('/cursos/entrega/arquivo/{id}/excluir', 'Modulos/ava/AvaActivityStudentController@deleteFile');
$router->get('/cursos/entrega/arquivo/{id}', 'Modulos/ava/AvaActivityStudentController@file');
$router->post('/cursos/aula/{id}/comentario', 'Modulos/ava/AvaCommentController@store');
$router->post('/cursos/comentario/{id}/excluir', 'Modulos/ava/AvaCommentController@delete');
$router->get('/certificado/validar/{codigo}', 'Modulos/ava/CertificateValidationController@validarCodigo');

$router->get('/cursos', 'Modulos/ava/AvaStudentController@index');
$router->get('/cursos/agenda', 'Modulos/ava/AvaStudentController@agenda');
$router->get('/cursos/anexo/{id}', 'Modulos/ava/AvaStudentController@anexo');
$router->post('/api/ava/aula/{id}/progresso-video', 'Modulos/ava/AvaStudentController@saveVideoProgress');
$router->get('/cursos/ao-vivo/{id}', 'Modulos/ava/AvaLiveStudentController@room');
$router->get('/cursos/certificados', 'Modulos/ava/AvaCertificateStudentController@index');
