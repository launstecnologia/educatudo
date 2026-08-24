<?php
// Painel admin master (multi-tenant): login e dashboard no banco master
$router->get('/master', 'Master/MasterAuthController@index');
$router->post('/master/setup', 'Master/MasterAuthController@setup');
$router->post('/master/login', 'Master/MasterAuthController@autenticar');
$router->get('/master/dashboard', 'Master/MasterAuthController@dashboard');
$router->get('/master/logout', 'Master/MasterAuthController@logout');
$router->get('/master/escolas', 'Master/MasterEscolasController@index');
$router->get('/master/escolas/criar', 'Master/MasterEscolasController@create');
$router->post('/master/escolas/salvar', 'Master/MasterEscolasController@store');
$router->get('/master/escolas/editar', 'Master/MasterEscolasController@edit');
$router->post('/master/escolas/atualizar', 'Master/MasterEscolasController@update');
$router->post('/master/escolas/manutencao', 'Master/MasterEscolasController@toggleManutencao');
$router->post('/master/escolas/verificar-dominio', 'Master/MasterEscolasController@verificarDominio');
$router->get('/master/escolas/verificar-dominios-cron', 'Master/MasterEscolasController@verificarDominiosCron');
$router->get('/master/migrations', 'Master/MasterMigrationsController@index');
$router->post('/master/migrations/executar-todas', 'Master/MasterMigrationsController@executarTodas');

// Performance Profiler (diagnóstico de queries/páginas lentas — só popula dado com APP_DEBUG=true)
$router->get('/master/performance', 'Master/MasterPerformanceController@index');
$router->post('/master/performance/toggle', 'Master/MasterPerformanceController@toggle');
$router->get('/master/performance/export', 'Master/MasterPerformanceController@export');

// Wiki documentação (doc_sistema/)
$router->get('/master/documentacao', 'Master/MasterDocSistemaController@index');
$router->get('/master/documentacao/{pagina}', 'Master/MasterDocSistemaController@index');

$router->get('/master/precificacao', 'Master/MasterPrecificacaoController@index');
$router->post('/master/precificacao/salvar', 'Master/MasterPrecificacaoController@salvar');
$router->get('/master/creditos-catalogo/tabelas', 'Master/MasterCreditosCatalogoController@tabelasIndex');
$router->post('/master/creditos-catalogo/tabelas/criar', 'Master/MasterCreditosCatalogoController@tabelaCriar');
$router->get('/master/creditos-catalogo/tabelas/{id}/editar', 'Master/MasterCreditosCatalogoController@tabelaEditar');
$router->post('/master/creditos-catalogo/tabelas/{id}/salvar-itens', 'Master/MasterCreditosCatalogoController@tabelaSalvarItens');
$router->post('/master/creditos-catalogo/tabelas/{id}/toggle', 'Master/MasterCreditosCatalogoController@tabelaToggle');
$router->post('/master/creditos-catalogo/tabelas/{id}/padrao', 'Master/MasterCreditosCatalogoController@tabelaDefinirPadrao');
$router->get('/master/creditos-catalogo/pacotes', 'Master/MasterCreditosCatalogoController@pacotesIndex');
$router->get('/master/creditos-catalogo/pacotes/{id}/dados', 'Master/MasterCreditosCatalogoController@pacoteDados');
$router->post('/master/creditos-catalogo/pacotes/salvar', 'Master/MasterCreditosCatalogoController@pacoteSalvar');
$router->post('/master/creditos-catalogo/pacotes/toggle', 'Master/MasterCreditosCatalogoController@pacoteToggle');
$router->get('/master/creditos-catalogo/planos', 'Master/MasterCreditosCatalogoController@planosIndex');
$router->get('/master/creditos-catalogo/planos/{id}/dados', 'Master/MasterCreditosCatalogoController@planoDados');
$router->post('/master/creditos-catalogo/planos/salvar', 'Master/MasterCreditosCatalogoController@planoSalvar');
$router->post('/master/creditos-catalogo/planos/toggle', 'Master/MasterCreditosCatalogoController@planoToggle');
$router->get('/master/asaas', 'Master/MasterAsaasConfigController@index');
$router->post('/master/asaas/salvar', 'Master/MasterAsaasConfigController@salvar');
$router->post('/master/asaas/reconciliar', 'Master/MasterAsaasConfigController@reconciliar');
$router->get('/master/asaas/reconciliar-cron', 'Master/MasterAsaasConfigController@reconciliarCron');
$router->get('/master/asaas/cancelar-pendentes-cron', 'Master/MasterAsaasConfigController@cancelarPendentesCron');
$router->get('/master/faturamento', 'Master/MasterFaturamentoController@index');
$router->get('/master/creditos/extrato', 'Master/MasterCreditosExtratoController@index');
$router->get('/master/llm-custos', 'Master/MasterLlmCustosController@index');
$router->get('/master/fila-ia', 'Master/MasterFilaIaController@index');
$router->get('/master/fila-ia/job', 'Master/MasterFilaIaController@job');
$router->get('/master/fila-ia/cron', 'Master/MasterFilaIaController@cron');
$router->post('/master/fila-ia/destravar', 'Master/MasterFilaIaController@destravar');
$router->post('/master/fila-ia/reenfileirar', 'Master/MasterFilaIaController@reenfileirar');
$router->get('/master/creditos/asaas/checkout', 'Master/MasterCreditosCheckoutController@iniciar');
$router->post('/master/creditos/asaas/checkout', 'Master/MasterCreditosCheckoutController@iniciar');
$router->post('/master/webhooks/asaas', 'Master/MasterAsaasWebhookController@handle');
$router->post('/webhooks/jaas/recording', 'Webhooks/JaasWebhookController@recording');
$router->get('/master/educa-hits', 'Master/MasterEducaHitsController@index');
$router->get('/master/educa-hits/pedidos', 'Master/MasterEducaHitsController@pedidos');
$router->get('/master/educa-hits/musicas', 'Master/MasterEducaHitsController@musicas');
$router->get('/master/educa-hits/cadastro', 'Master/MasterEducaHitsController@cadastro');
$router->get('/master/educa-hits/configuracao', 'Master/MasterEducaHitsController@configuracao');
$router->get('/master/educa-hits/player', 'Master/MasterEducaHitsController@pedidos');
$router->post('/master/educa-hits/deliver', 'Master/MasterEducaHitsController@deliver');
$router->post('/master/educa-hits/request-status', 'Master/MasterEducaHitsController@updateRequestStatus');
$router->post('/master/educa-hits/requests-bulk-status', 'Master/MasterEducaHitsController@bulkRequestStatus');
$router->post('/master/educa-hits/delete-song', 'Master/MasterEducaHitsController@deleteSong');
$router->get('/master/usuarios', 'Master/MasterUsuariosController@index');
$router->get('/master/usuarios/criar', 'Master/MasterUsuariosController@create');
$router->post('/master/usuarios/salvar', 'Master/MasterUsuariosController@store');
$router->get('/master/usuarios/editar', 'Master/MasterUsuariosController@edit');
$router->post('/master/usuarios/atualizar', 'Master/MasterUsuariosController@update');
$router->post('/master/usuarios/desativar', 'Master/MasterUsuariosController@desativar');
$router->post('/master/migrations/executar-escola', 'Master/MasterMigrationsController@executarEscola');
    $router->post('/master/migrations/executar-escola-selecionadas', 'Master/MasterMigrationsController@executarEscolaSelecionadas');
    $router->post('/master/migrations/marcar-executadas', 'Master/MasterMigrationsController@marcarExecutadas');
    $router->post('/master/migrations/executar-master', 'Master/MasterMigrationsController@executarMaster');
    $router->post('/master/migrations/executar-master-selecionadas', 'Master/MasterMigrationsController@executarMasterSelecionadas');

// Detalhes da escola (Master)
$router->get('/master/escolas/{id}/detalhes', 'Master/MasterEscolaDetailController@visaoGeral');
$router->get('/master/escolas/{id}/provas-ao-vivo', 'Master/MasterEscolaDetailController@provasAoVivo');
$router->get('/master/escolas/{id}/usuarios', 'Master/MasterEscolaDetailController@usuarios');
$router->get('/master/escolas/{id}/professores', 'Master/MasterEscolaDetailController@professores');
$router->get('/master/escolas/{id}/alunos', 'Master/MasterEscolaDetailController@alunos');
$router->post('/master/escolas/{id}/usuarios/criar', 'Master/MasterEscolaDetailController@usuarioStore');
$router->post('/master/escolas/{id}/usuarios/atualizar', 'Master/MasterEscolaDetailController@usuarioUpdate');
$router->get('/master/escolas/{id}/modulos', 'Master/MasterEscolaDetailController@modulos');
$router->post('/master/escolas/{id}/modulos', 'Master/MasterEscolaDetailController@modulosSalvar');
$router->get('/master/escolas/{id}/creditos', 'Master/MasterEscolaDetailController@creditos');
$router->post('/master/escolas/{id}/creditos', 'Master/MasterEscolaDetailController@creditosSalvar');
$router->post('/master/escolas/{id}/creditos/renovar', 'Master/MasterEscolaDetailController@creditosRenovarTodos');
$router->post('/master/escolas/{id}/creditos/catalogo-vinculos', 'Master/MasterEscolaDetailController@creditosCatalogoVinculosSalvar');
$router->get('/master/escolas/{id}/layout', 'Master/MasterEscolaDetailController@layout');
$router->post('/master/escolas/{id}/layout', 'Master/MasterEscolaDetailController@layoutSalvar');
$router->get('/master/escolas/{id}/sliders', 'Master/MasterEscolaDetailController@sliders');
$router->post('/master/escolas/{id}/sliders', 'Master/MasterEscolaDetailController@slidersSalvar');
$router->get('/master/escolas/{id}/exportar-config-json', 'Master/MasterEscolaDetailController@exportarConfigJson');
$router->post('/master/escolas/{id}/importar-config-json', 'Master/MasterEscolaDetailController@importarConfigJson');
$router->get('/master/escolas/{id}/links-uteis', 'Master/MasterEscolaDetailController@linksUteis');
$router->post('/master/escolas/{id}/links-uteis', 'Master/MasterEscolaDetailController@linksUteisSalvar');
$router->get('/master/escolas/{id}/apps-externos', 'Master/MasterEscolaDetailController@appsExternos');
$router->post('/master/escolas/{id}/apps-externos', 'Master/MasterEscolaDetailController@appsExternosSalvar');
$router->get('/master/escolas/{id}/prompts', 'Master/MasterEscolaDetailController@prompts');
$router->post('/master/escolas/{id}/prompts', 'Master/MasterEscolaDetailController@promptsSalvar');
$router->get('/master/entrar-como', 'Master/MasterEscolaDetailController@entrarComo');
$router->get('/master/logs', 'Master/MasterLogsController@index');
$router->get('/master/log-provas', 'Master/MasterLogProvasController@index');
$router->get('/master/tickets', 'Master/MasterTicketsController@index');
$router->get('/master/tickets/dados', 'Master/MasterTicketsController@dados');
$router->get('/master/tickets/ver', 'Master/MasterTicketsController@exibir');
$router->post('/master/tickets/responder', 'Master/MasterTicketsController@responder');
$router->post('/master/tickets/fechar', 'Master/MasterTicketsController@fechar');
$router->post('/master/tickets/em-andamento', 'Master/MasterTicketsController@marcarAndamento');
$router->get('/master/escolas/{id}/limites', 'Master/MasterEscolaDetailController@limites');
$router->post('/master/escolas/{id}/limites', 'Master/MasterEscolaDetailController@limitesSalvar');
$router->get('/master/escolas/{id}/banco', 'Master/MasterEscolaDetailController@banco');

// Master: teste de exercícios (página sem login)
$router->get('/master/teste-exercicios', 'Master/MasterTesteExerciciosController@index');
$router->post('/master/teste-exercicios/gerar', 'Master/MasterTesteExerciciosController@gerar');
// No subdomínio master: master.educatudo.com/teste-exercicios
$router->get('/teste-exercicios', 'Master/MasterTesteExerciciosController@index');
$router->post('/teste-exercicios/gerar', 'Master/MasterTesteExerciciosController@gerar');
