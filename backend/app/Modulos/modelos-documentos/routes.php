<?php
/**
 * Rotas admin do módulo Modelos de Documentos.
 *
 * @var Router $router
 */

$router->get('/admin/modelos-documentos', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@index');
$router->get('/admin/paineis/documentos', 'Modulos/modelos-documentos/ArquivoEmissaoDocumentoAdminController@index');
$router->post('/admin/paineis/documentos/gerar', 'Modulos/modelos-documentos/ArquivoEmissaoDocumentoAdminController@gerar');
$router->get('/admin/paineis/documentos/{id}/pdf', 'Modulos/modelos-documentos/ArquivoEmissaoDocumentoAdminController@pdf');
$router->get('/admin/modelos-documentos/emitir-lote', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@emitirLote');
$router->get('/admin/modelos-documentos/emitir-lote/pdf', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@emitirLotePdf');
$router->get('/admin/modelos-documentos/importar', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@importar');
$router->post('/admin/modelos-documentos/importar', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@processarImportacao');
$router->post('/admin/modelos-documentos/importar/catalogo', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@aplicarCatalogo');
$router->get('/admin/modelos-documentos/importar/conferir', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@conferirImportacao');
$router->post('/admin/modelos-documentos/importar/conferir', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@confirmarImportacao');
$router->get('/admin/modelos-documentos/layout', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@layout');
$router->post('/admin/modelos-documentos/layout', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@salvarLayout');
$router->get('/admin/modelos-documentos/editor', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@editor');
$router->get('/admin/modelos-documentos/demonstracao', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@demonstracao');
$router->post('/admin/modelos-documentos/reproduzir-imagem', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@reproduzirImagem');
$router->post('/admin/modelos-documentos/assistente', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@assistente');
$router->post('/admin/modelos-documentos/estrutura', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@salvarEstruturaNovo');
$router->get('/admin/modelos-documentos/create', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@create');
$router->post('/admin/modelos-documentos', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@store');
$router->get('/admin/modelos-documentos/{id}/editor', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@editor');
$router->post('/admin/modelos-documentos/{id}/estrutura', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@salvarEstrutura');
$router->get('/admin/modelos-documentos/{id}/edit', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@edit');
$router->post('/admin/modelos-documentos/{id}/edit', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@update');
$router->get('/admin/modelos-documentos/{id}/preview', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@preview');
$router->post('/admin/modelos-documentos/excluir', 'Modulos/modelos-documentos/ModeloDocumentoAdminController@destroy');

$router->get('/admin/students/{id}/declaracoes/{tipo}/pdf', 'Declarations/AdminDeclarationController@gerarPdf');
$router->get('/admin/students/{id}/historico-escolar', 'Declarations/AdminHistoricoEscolarController@index');
$router->post('/admin/students/{id}/historico-escolar/gerar', 'Declarations/AdminHistoricoEscolarController@gerarRascunho');
$router->get('/admin/students/{id}/historico-escolar/{historicoId}', 'Declarations/AdminHistoricoEscolarController@show');
$router->get('/admin/students/{id}/historico-escolar/{historicoId}/pdf', 'Declarations/AdminHistoricoEscolarController@pdf');
$router->post('/admin/students/{id}/historico-escolar/{historicoId}/conferir', 'Declarations/AdminHistoricoEscolarController@conferir');
$router->post('/admin/students/{id}/historico-escolar/{historicoId}/voltar-rascunho', 'Declarations/AdminHistoricoEscolarController@voltarRascunho');
$router->post('/admin/students/{id}/historico-escolar/{historicoId}/emitir', 'Declarations/AdminHistoricoEscolarController@emitir');
$router->post('/admin/students/{id}/historico-escolar/{historicoId}/assinar', 'Declarations/AdminHistoricoEscolarController@assinar');
$router->post('/admin/students/{id}/historico-escolar/{historicoId}/nova-versao', 'Declarations/AdminHistoricoEscolarController@novaVersao');
$router->post('/admin/students/{id}/historico-escolar/{historicoId}/itens-externos', 'Declarations/AdminHistoricoEscolarController@adicionarItemExterno');
$router->post('/admin/students/{id}/historico-escolar/{historicoId}/itens/{itemId}/excluir', 'Declarations/AdminHistoricoEscolarController@excluirItemExterno');
$router->post('/admin/students/{id}/historico-escolar/{historicoId}/observacoes', 'Declarations/AdminHistoricoEscolarController@salvarObservacoes');
$router->post('/admin/students/{id}/historico-escolar/{historicoId}/resultado', 'Declarations/AdminHistoricoEscolarController@salvarResultado');

$router->get('/admin/documentos-institucionais', 'Admin/InstitutionalDocsController@index');
$router->post('/admin/documentos-institucionais/salvar', 'Admin/InstitutionalDocsController@salvar');
$router->post('/admin/documentos-institucionais/excluir', 'Admin/InstitutionalDocsController@excluir');
$router->get('/admin/documentos-institucionais/baixar', 'Admin/InstitutionalDocsController@baixar');

$router->get('/admin/configuracao/ui-modelos', 'Admin/UiModelosController@index');
$router->get('/admin/configuracao/ui-modelos/botoes', 'Admin/UiModelosController@botoes');
$router->get('/admin/configuracao/ui-modelos/tabela', 'Admin/UiModelosController@tabela');
$router->get('/admin/configuracao/ui-modelos/formulario', 'Admin/UiModelosController@formulario');
$router->post('/admin/configuracao/ui-modelos/formulario', 'Admin/UiModelosController@formularioEnviar');
$router->get('/admin/configuracao/ui-modelos/offcanvas', 'Admin/UiModelosController@offcanvas');
$router->get('/admin/configuracao/ui-modelos/badges', 'Admin/UiModelosController@badges');
$router->get('/admin/configuracao/ui-modelos/wizard', 'Admin/UiModelosController@wizard');
$router->post('/admin/configuracao/ui-modelos/wizard', 'Admin/UiModelosController@wizardEnviar');
