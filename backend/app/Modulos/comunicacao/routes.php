<?php
/**
 * Rotas autenticadas do módulo Comunicação.
 *
 * @var Router $router
 */

// Hub do menu
$router->get('/admin/comunicacao', 'Modulos/comunicacao/ComunicacaoAdminController@index');

// Comunicação escolar
$router->get('/admin/comunicacao-escolar', 'Modulos/comunicacao/SchoolCommunicationController@index');
$router->get('/admin/comunicacao-escolar/nova', 'Modulos/comunicacao/SchoolCommunicationController@create');
$router->post('/admin/comunicacao-escolar', 'Modulos/comunicacao/SchoolCommunicationController@store');
$router->get('/admin/comunicacao-escolar/{id}', 'Modulos/comunicacao/SchoolCommunicationController@show');
$router->post('/admin/comunicacao-escolar/{id}/responder', 'Modulos/comunicacao/SchoolCommunicationController@reply');

// Calendário escolar (mesmo controller; feature_key no catálogo nativo)
$router->get('/admin/calendario-escolar', 'Modulos/comunicacao/SchoolCommunicationController@calendar');
$router->get('/admin/calendario-escolar/novo', 'Modulos/comunicacao/SchoolCommunicationController@calendarCreate');
$router->get('/admin/calendario-escolar/{id}/dados', 'Modulos/comunicacao/SchoolCommunicationController@calendarDados');
$router->get('/admin/calendario-escolar/{id}/editar', 'Modulos/comunicacao/SchoolCommunicationController@calendarEdit');
$router->post('/admin/calendario-escolar', 'Modulos/comunicacao/SchoolCommunicationController@calendarStore');
$router->post('/admin/calendario-escolar/{id}', 'Modulos/comunicacao/SchoolCommunicationController@calendarUpdate');
$router->post('/admin/calendario-escolar/{id}/cancelar', 'Modulos/comunicacao/SchoolCommunicationController@calendarCancel');

// Reuniões
$router->get('/admin/reunioes/aluno', 'Modulos/comunicacao/MeetingController@alunoIndex');
$router->post('/admin/reunioes/aluno/salvar', 'Modulos/comunicacao/MeetingController@alunoSalvar');
$router->post('/admin/reunioes/aluno/excluir', 'Modulos/comunicacao/MeetingController@alunoExcluir');
$router->get('/admin/reunioes/geral', 'Modulos/comunicacao/MeetingController@geralIndex');
$router->post('/admin/reunioes/geral/salvar', 'Modulos/comunicacao/MeetingController@geralSalvar');
$router->post('/admin/reunioes/geral/atualizar', 'Modulos/comunicacao/MeetingController@geralAtualizar');
$router->post('/admin/reunioes/geral/excluir', 'Modulos/comunicacao/MeetingController@geralExcluir');
$router->get('/admin/reunioes/geral/{id}/dados', 'Modulos/comunicacao/MeetingController@geralDados');
$router->get('/admin/reunioes/geral/{id}/pdf', 'Modulos/comunicacao/MeetingController@geralPdf');
