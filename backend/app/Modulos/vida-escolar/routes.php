<?php
/**
 * Rotas autenticadas do módulo Vida Escolar.
 *
 * @var Router $router
 */

$router->get('/admin/vida-escolar', 'Modulos/vida-escolar/VidaEscolarAdminController@index');
$router->get('/admin/vida-escolar/oficios', 'Modulos/vida-escolar/OficioAdminController@index');
$router->get('/admin/vida-escolar/oficios/novo', 'Modulos/vida-escolar/OficioAdminController@form');
$router->get('/admin/vida-escolar/oficios/alunos', 'Modulos/vida-escolar/OficioAdminController@alunosJson');
$router->post('/admin/vida-escolar/oficios', 'Modulos/vida-escolar/OficioAdminController@salvar');
$router->get('/admin/vida-escolar/oficios/{id}/editar', 'Modulos/vida-escolar/OficioAdminController@form');
$router->get('/admin/vida-escolar/oficios/{id}/pdf', 'Modulos/vida-escolar/OficioAdminController@pdf');
$router->post('/admin/vida-escolar/oficios/{id}/emitir', 'Modulos/vida-escolar/OficioAdminController@emitir');
$router->post('/admin/vida-escolar/oficios/{id}/cancelar', 'Modulos/vida-escolar/OficioAdminController@cancelar');
$router->post('/admin/vida-escolar/oficios/{id}', 'Modulos/vida-escolar/OficioAdminController@salvar');
$router->get('/admin/students/{id}/vida-escolar', 'Modulos/vida-escolar/VidaEscolarAdminController@aluno');
$router->post('/admin/students/{id}/vida-escolar/garantir', 'Modulos/vida-escolar/VidaEscolarAdminController@garantir');
$router->post('/admin/students/{id}/vida-escolar/alimentar', 'Modulos/vida-escolar/VidaEscolarAdminController@alimentar');
$router->post('/admin/students/{id}/vida-escolar/celula/{celulaId}', 'Modulos/vida-escolar/VidaEscolarAdminController@salvarCelula');
$router->post('/admin/students/{id}/vida-escolar/fechar-bimestre', 'Modulos/vida-escolar/VidaEscolarAdminController@fecharBimestre');
$router->post('/admin/students/{id}/vida-escolar/homologar', 'Modulos/vida-escolar/VidaEscolarAdminController@homologar');
$router->post('/admin/students/{id}/vida-escolar/reabrir', 'Modulos/vida-escolar/VidaEscolarAdminController@reabrir');
$router->get('/admin/students/{id}/vida-escolar/pdf', 'Modulos/vida-escolar/VidaEscolarAdminController@boletimPdf');
$router->get('/admin/students/{id}/vida-escolar/pacote-transferencia', 'Modulos/vida-escolar/VidaEscolarAdminController@pacoteTransferencia');
$router->get('/admin/students/{id}/vida-escolar/dossie', 'Modulos/vida-escolar/VidaEscolarAdminController@dossie');
$router->get('/admin/students/{id}/vida-escolar/sed', 'Modulos/vida-escolar/VidaEscolarAdminController@sed');
$router->get('/admin/students/{id}/vida-escolar/documento/{documentoId}/arquivo', 'Modulos/vida-escolar/VidaEscolarAdminController@arquivoDocumento');
$router->post('/admin/students/{id}/vida-escolar/ano-externo', 'Modulos/vida-escolar/VidaEscolarAdminController@anoExterno');
$router->post('/admin/students/{id}/vida-escolar/documento', 'Modulos/vida-escolar/VidaEscolarAdminController@documento');
$router->post('/admin/students/{id}/vida-escolar/documento/{documentoId}/ler', 'Modulos/vida-escolar/VidaEscolarAdminController@lerHistorico');
$router->get('/admin/students/{id}/vida-escolar/importar', 'Modulos/vida-escolar/VidaEscolarAdminController@importar');
$router->post('/admin/students/{id}/vida-escolar/importar', 'Modulos/vida-escolar/VidaEscolarAdminController@salvarImportacao');
$router->post('/admin/students/{id}/vida-escolar/importar/{importacaoId}/validar', 'Modulos/vida-escolar/VidaEscolarAdminController@validarImportacao');
