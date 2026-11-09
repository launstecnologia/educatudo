<?php
/**
 * Rotas admin do fechamento oficial, livro de homologações e documentos do período.
 *
 * @var Router $router
 */

$router->get('/admin/fechamento', 'Modulos/fechamento/FechamentoAdminController@index');
$router->get('/admin/fechamento/painel', 'Modulos/fechamento/FechamentoAdminController@painelLista');
$router->get('/admin/fechamento/impressao-lote', 'Modulos/fechamento/FechamentoAdminController@impressaoLote');
$router->get('/admin/fechamento/apoio-digitacao', 'Modulos/fechamento/FechamentoAdminController@apoioDigitacao');
$router->post('/admin/fechamento/apoio-digitacao/identificadores', 'Modulos/fechamento/FechamentoAdminController@salvarCodigoApoioDigitacao');
$router->post('/admin/fechamento/apoio-digitacao/emitir', 'Modulos/fechamento/FechamentoAdminController@emitirApoioDigitacao');
$router->post('/admin/fechamento/apoio-digitacao/status', 'Modulos/fechamento/FechamentoAdminController@statusApoioDigitacao');
$router->get('/admin/fechamento/apoio-digitacao/arquivo/{id}/{formato}', 'Modulos/fechamento/FechamentoAdminController@arquivoApoioDigitacao');
$router->post('/admin/fechamento/impressao-lote/enfileirar', 'Modulos/fechamento/FechamentoAdminController@enfileirarImpressao');
$router->get('/admin/fechamento/impressao-lote/arquivo/{id}', 'Modulos/fechamento/FechamentoAdminController@arquivoImpressao');
$router->get('/admin/fechamento/impressao-lote/pdf', 'Modulos/fechamento/FechamentoAdminController@abrirPdf');
$router->post('/admin/fechamento/impressao-lote/preparar-historicos', 'Modulos/fechamento/FechamentoAdminController@prepararHistoricos');
$router->get('/admin/fechamento/turma/{id}/homologacoes/dados', 'Modulos/fechamento/FechamentoAdminController@dadosHomologacoes');
$router->get('/admin/fechamento/turma/{id}/documentos/dados', 'Modulos/fechamento/FechamentoAdminController@dadosDocumentos');
$router->get('/admin/fechamento/turma/{id}', 'Modulos/fechamento/FechamentoAdminController@turma');
$router->post('/admin/fechamento/turma/{id}/iniciar', 'Modulos/fechamento/FechamentoAdminController@iniciar');
$router->post('/admin/fechamento/turma/{id}/reabrir', 'Modulos/fechamento/FechamentoAdminController@reabrir');
$router->post('/admin/fechamento/turma/{id}/homologar', 'Modulos/fechamento/FechamentoAdminController@homologar');
$router->post('/admin/fechamento/turma/{id}/retificar', 'Modulos/fechamento/FechamentoAdminController@retificar');
$router->get('/admin/homologacoes', 'Modulos/fechamento/FechamentoAdminController@homologacoes');
$router->get('/admin/documentos-periodo', 'Modulos/fechamento/FechamentoAdminController@documentos');
$router->get('/admin/diagnostico-menu', 'Modulos/fechamento/FechamentoAdminController@diagnosticoMenu');
$router->get('/admin/implantar-academico', 'Modulos/fechamento/FechamentoAdminController@implantar');

$router->get('/admin/resultados-finais', 'Admin/ResultadoFinalAdminController@index');
$router->get('/admin/resultados-finais/relatorios', 'Admin/ResultadoFinalAdminController@relatorios');
$router->get('/admin/resultados-finais/relatorios/pdf', 'Admin/ResultadoFinalAdminController@relatorioPdf');
$router->get('/admin/resultados-finais/relatorios/csv', 'Admin/ResultadoFinalAdminController@relatorioCsv');
$router->get('/admin/resultados-finais/layouts', 'Admin/ResultadoFinalAdminController@layouts');
$router->post('/admin/resultados-finais/layouts', 'Admin/ResultadoFinalAdminController@salvarLayouts');
$router->get('/admin/resultados-finais/turma/{id}', 'Admin/ResultadoFinalAdminController@turma');
$router->post('/admin/resultados-finais/turma/{id}/homologar', 'Admin/ResultadoFinalAdminController@homologar');
$router->post('/admin/resultados-finais/turma/{id}/especial', 'Admin/ResultadoFinalAdminController@especial');
$router->post('/admin/resultados-finais/turma/{id}/especial/{especialId}/excluir', 'Admin/ResultadoFinalAdminController@excluirEspecial');
$router->get('/admin/resultados-finais/turma/{id}/ata', 'Admin/ResultadoFinalAdminController@ata');
$router->get('/admin/resultados-finais/turma/{id}/ata/pdf', 'Admin/ResultadoFinalAdminController@ataPdf');
$router->post('/admin/resultados-finais/resultado/{id}/reabrir', 'Admin/ResultadoFinalAdminController@reabrir');
$router->get('/admin/resultados-finais/aluno/{id}/ficha', 'Admin/ResultadoFinalAdminController@ficha');
$router->get('/admin/resultados-finais/aluno/{id}/ficha/pdf', 'Admin/ResultadoFinalAdminController@fichaPdf');
$router->get('/admin/resultados-finais/aluno/{id}/boletim/pdf', 'Admin/ResultadoFinalAdminController@boletimPdf');
