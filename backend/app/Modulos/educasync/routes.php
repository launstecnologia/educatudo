<?php
/**
 * Rotas autenticadas do módulo EducaSync (admin).
 *
 * @var Router $router
 */

$router->get('/admin/educasync', 'Modulos/educasync/EducaSyncAdminController@index');
$router->get('/admin/educasync/novo', 'Modulos/educasync/EducaSyncAdminController@novo');
$router->post('/admin/educasync', 'Modulos/educasync/EducaSyncAdminController@salvar');
$router->get('/admin/educasync/{id}', 'Modulos/educasync/EducaSyncAdminController@show');
$router->post('/admin/educasync/{id}/slots', 'Modulos/educasync/EducaSyncAdminController@gerarSlots');
$router->post('/admin/educasync/{id}/slots/{slotId}/excluir', 'Modulos/educasync/EducaSyncAdminController@excluirSlot');
$router->post('/admin/educasync/{id}/ano-letivo', 'Modulos/educasync/EducaSyncAdminController@atualizarAnoLetivo');
$router->post('/admin/educasync/{id}/demandas', 'Modulos/educasync/EducaSyncAdminController@salvarDemanda');
$router->post('/admin/educasync/{id}/demandas/importar-matriz', 'Modulos/educasync/EducaSyncAdminController@importarMatriz');
$router->post('/admin/educasync/{id}/demandas/excluir-todas', 'Modulos/educasync/EducaSyncAdminController@excluirTodasDemandas');
$router->post('/admin/educasync/{id}/demandas/{demandaId}/excluir', 'Modulos/educasync/EducaSyncAdminController@excluirDemanda');
$router->post('/admin/educasync/{id}/disponibilidades/professor', 'Modulos/educasync/EducaSyncAdminController@salvarDisponibilidadeProfessor');
$router->post('/admin/educasync/{id}/disponibilidades/professor/limpar', 'Modulos/educasync/EducaSyncAdminController@limparDisponibilidadeProfessor');
$router->post('/admin/educasync/{id}/assistente/mensagem', 'Modulos/educasync/EducaSyncAssistenteController@mensagem');
$router->post('/admin/educasync/{id}/assistente/confirmar-proposta', 'Modulos/educasync/EducaSyncAssistenteController@confirmarProposta');
$router->post('/admin/educasync/{id}/gerar', 'Modulos/educasync/EducaSyncAdminController@gerar');
$router->get('/admin/educasync/{id}/execucoes/{execucaoId}/status', 'Modulos/educasync/EducaSyncAdminController@status');
$router->get('/admin/educasync/{id}/versoes/{versaoId}', 'Modulos/educasync/EducaSyncAdminController@versao');
$router->post('/admin/educasync/{id}/versoes/{versaoId}/reotimizar', 'Modulos/educasync/EducaSyncAdminController@reotimizar');
$router->post('/admin/educasync/{id}/versoes/{versaoId}/publicar', 'Modulos/educasync/EducaSyncAdminController@publicar');
