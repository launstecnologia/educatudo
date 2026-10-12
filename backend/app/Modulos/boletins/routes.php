<?php
/**
 * Rotas admin do cadastro de Modelo de Boletim.
 *
 * @var Router $router
 */

$router->get('/admin/boletins', 'Modulos/boletins/BoletimCadastroAdminController@index');
$router->get('/admin/boletins/novo', 'Modulos/boletins/BoletimCadastroAdminController@novo');
$router->post('/admin/boletins', 'Modulos/boletins/BoletimCadastroAdminController@salvar');
$router->get('/admin/boletins/{id}/editar', 'Modulos/boletins/BoletimCadastroAdminController@editar');
$router->post('/admin/boletins/{id}/update', 'Modulos/boletins/BoletimCadastroAdminController@atualizar');
$router->post('/admin/boletins/{id}/delete', 'Modulos/boletins/BoletimCadastroAdminController@excluir');
$router->get('/admin/boletins/{id}/gerar-avaliacoes', 'Modulos/boletins/BoletimCadastroAdminController@gerarAvaliacoes');
$router->post('/admin/boletins/{id}/gerar-avaliacoes', 'Modulos/boletins/BoletimCadastroAdminController@gerarAvaliacoesExecutar');
$router->get('/admin/boletins/{id}/gerar-boletins', 'Modulos/boletins/BoletimCadastroAdminController@gerarBoletinsPagina');
$router->get('/admin/boletins/{id}/simular', 'Modulos/boletins/BoletimCadastroAdminController@simularBoletim');
$router->post('/admin/boletins/{id}/gerar', 'Modulos/boletins/BoletimCadastroAdminController@gerarDocumento');

// Listagem / arquivo / geração
$router->get('/admin/boletim', 'Modulos/boletins/BoletimConfigController@listagem');
$router->get('/admin/boletim/arquivo', 'Modulos/boletins/BoletimConfigController@arquivo');
$router->get('/admin/boletim/geracao-status', 'Modulos/boletins/BoletimConfigController@geracaoStatusJson');
$router->post('/admin/boletim/cancelar-geracao', 'Modulos/boletins/BoletimConfigController@cancelarGeracaoBoletim');
$router->get('/admin/boletim-guia', 'Modulos/boletins/BoletimGuiaController@index');

// Configuração e assistente
$router->get('/admin/boletim-configuracao', 'Modulos/boletins/BoletimConfigController@index');
$router->get('/admin/boletim-configuracao/assistente', 'Modulos/boletins/BoletimConfigController@assistente');
$router->get('/admin/boletim-configuracao/jornadas', 'Modulos/boletins/BoletimConfigController@jornadasJson');
$router->get('/admin/boletim-configuracao/evento-componentes', 'Modulos/boletins/BoletimConfigController@eventoComponentesJson');
$router->get('/admin/boletim-configuracao/keepalive', 'Modulos/boletins/BoletimConfigController@keepalive');
$router->post('/admin/boletim-configuracao/salvar', 'Modulos/boletins/BoletimConfigController@salvarRegra');
$router->post('/admin/boletim-configuracao/restaurar-config-versao', 'Modulos/boletins/BoletimConfigController@restaurarConfigVersao');
$router->post('/admin/boletim-configuracao/renomear', 'Modulos/boletins/BoletimConfigController@renomearRegra');
$router->post('/admin/boletim-configuracao/excluir-regra', 'Modulos/boletins/BoletimConfigController@excluirRegra');
$router->post('/admin/boletim-configuracao/duplicar-regra', 'Modulos/boletins/BoletimConfigController@duplicarRegra');
$router->post('/admin/boletim-configuracao/visibilidade-regra', 'Modulos/boletins/BoletimConfigController@alternarVisibilidadeRegra');
$router->post('/admin/boletim-configuracao/desabilitar-lista', 'Modulos/boletins/BoletimConfigController@desabilitarListaAvaliacoes');
$router->post('/admin/boletim-configuracao/notas-manuais', 'Modulos/boletins/BoletimConfigController@salvarNotasManuais');
$router->post('/admin/boletim-configuracao/nota-manual-materia-ajax', 'Modulos/boletins/BoletimConfigController@salvarNotaManualMateriaAjax');
$router->post('/admin/boletim-configuracao/gerar-boletins', 'Modulos/boletins/BoletimConfigController@gerarBoletins');
$router->post('/admin/boletim-configuracao/sincronizar-vida-escolar-salvos', 'Modulos/boletins/BoletimConfigController@sincronizarVidaEscolarSalvos');
$router->get('/admin/boletim-configuracao/checklist-pre-geracao', 'Modulos/boletins/BoletimConfigController@checklistPreGeracao');
$router->get('/admin/boletim-configuracao/logs-geracao', 'Modulos/boletins/BoletimConfigController@logsGeracaoJson');
$router->get('/admin/boletim-configuracao/geracao-detalhe', 'Modulos/boletins/BoletimConfigController@geracaoDetalheJson');
$router->post('/admin/boletim-configuracao/restaurar-geracao', 'Modulos/boletins/BoletimConfigController@restaurarGeracao');
$router->post('/admin/boletim-configuracao/travar-aluno', 'Modulos/boletins/BoletimConfigController@travarAluno');
$router->post('/admin/boletim-configuracao/destravar-aluno', 'Modulos/boletins/BoletimConfigController@destravarAluno');
$router->post('/admin/boletim-configuracao/simular-lote', 'Modulos/boletins/BoletimConfigController@simularLote');
$router->post('/admin/boletim-configuracao/publicar-boletim-aluno', 'Modulos/boletins/BoletimConfigController@publicarBoletimAlunoSimulado');
$router->post('/admin/boletim-configuracao/atualizar-boletins-gravados', 'Modulos/boletins/BoletimConfigController@atualizarBoletinsGravados');
$router->get('/admin/boletim-configuracao/gerados', 'Modulos/boletins/BoletimConfigController@boletinsGerados');
$router->get('/admin/boletim-configuracao/gerados/exportar', 'Modulos/boletins/BoletimConfigController@exportarBoletinsGerados');
$router->get('/admin/boletim-configuracao/gerados/preview', 'Modulos/boletins/BoletimConfigController@boletimGeradoPreview');
$router->post('/admin/boletim-configuracao/gerados/excluir', 'Modulos/boletins/BoletimConfigController@excluirBoletimGeradoAdmin');
$router->post('/admin/boletim-configuracao/gerados/excluir-lote', 'Modulos/boletins/BoletimConfigController@excluirBoletimGeradoLote');
$router->get('/admin/boletim-configuracao/assistente/contexto', 'Modulos/boletins/BoletimAssistenteController@contexto');
$router->post('/admin/boletim-configuracao/assistente/mensagem', 'Modulos/boletins/BoletimAssistenteController@mensagem');
$router->post('/admin/boletim-configuracao/assistente/mensagem-stream', 'Modulos/boletins/BoletimAssistenteController@mensagemStream');
$router->post('/admin/boletim-configuracao/assistente/ferramenta', 'Modulos/boletins/BoletimAssistenteController@ferramenta');
$router->post('/admin/boletim-configuracao/assistente/wizard/inicio', 'Modulos/boletins/BoletimAssistenteController@wizardInicio');
$router->post('/admin/boletim-configuracao/assistente/wizard/montar', 'Modulos/boletins/BoletimAssistenteController@wizardMontar');
$router->post('/admin/boletim-configuracao/assistente/consulta', 'Modulos/boletins/BoletimAssistenteController@consulta');
