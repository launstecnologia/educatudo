<?php
// Jornadas → Modulos/jornadas/routes.php
$router->get('/admin/ai-job/{id}/status', 'AIJobController@status');
// Dashboard: rota em app/Modulos/dashboard-gestao/routes.php
$router->get('/admin/creditos/pacotes', 'Admin/SchoolSettingsAdminController@creditosPacotes');
$router->post('/admin/creditos/pacotes', 'Admin/SchoolSettingsAdminController@creditosPacotesSalvar');
$router->post('/admin/creditos/pacotes/toggle', 'Admin/SchoolSettingsAdminController@creditosPacotesToggle');
$router->get('/admin/tudicoins', 'Admin/AdminCreditosController@carteiraEscola');
$router->post('/admin/tudicoins/comprar', 'Admin/AdminCreditosController@comprar');
$router->get('/admin/tudicoins/aguardando/{id}', 'Admin/AdminCreditosController@aguardando');
$router->post('/admin/tudicoins/pagar/{id}', 'Admin/AdminCreditosController@pagar');
$router->post('/admin/tudicoins/verificar/{id}', 'Admin/AdminCreditosController@verificar');
$router->get('/admin/tudicoins/status/{id}', 'Admin/AdminCreditosController@status');
// Apostilas → Modulos/apostilas/routes.php

// IA da Apostila (módulo novo, independente do módulo Apostilas acima)
$router->get('/admin/maintenance/painel', 'Admin/StudentAdminController@painelManutencao');
$router->post('/admin/maintenance/toggle', 'Admin/StudentAdminController@toggleMaintenance');
// Ocorrências extras → Modulos/ocorrencias/routes.php

// Redações → Modulos/redacoes/routes.php

// Registros de entrada/saída facial por aluno
$router->get('/admin/reconhecimento-facial/alunos/{id}/eventos', 'Admin/FacialRecognitionController@studentEvents');

// Gestão de Alunos
$router->get('/admin/students', 'Admin/StudentAdminController@alunos');
$router->get('/admin/students/transfer', 'Admin/StudentAdminController@transferenciaAlunos');
$router->get('/admin/students/export-csv', 'Admin/StudentAdminController@exportarAlunosCSV');
$router->get('/admin/students/export-historico-turmas', 'Admin/StudentAdminController@exportarHistoricoTurmasCSV');
$router->get('/admin/students/export-censo', 'Admin/StudentAdminController@exportarCensoCSV');
$router->get('/admin/students/template-csv', 'User/AdminController@templateCsvAlunos');
$router->get('/admin/students/create', 'Admin/StudentAdminController@criarAluno');
$router->post('/admin/students', 'Admin/StudentAdminController@salvarAluno');
$router->post('/admin/students/upload-excel', 'Admin/StudentAdminController@uploadExcelAlunos');
$router->post('/admin/students/import-responsaveis-csv', 'Admin/StudentAdminController@importarResponsaveisCsv');
$router->get('/admin/students/import-responsaveis-relatorio/{arquivo}', 'Admin/StudentAdminController@baixarRelatorioImportResponsaveis');
$router->get('/admin/students/exercicio-ia/{sessaoId}', 'Admin/StudentAdminController@verDetalhesExercicioIA');
$router->post('/admin/students/excluir-lista-exercicio-ia', 'Admin/StudentAdminController@excluirListaExercicioIA');
$router->get('/admin/students/{id}', 'Admin/StudentAdminController@mostrarAluno');
$router->get('/admin/students/{id}/tab/provas', 'Admin/StudentAdminController@provasTabFragment');
$router->get('/admin/students/{id}/auditoria', 'Admin/StudentAdminController@auditoriaAluno');
$router->post('/admin/students/{id}/boletim/observacao', 'Admin/StudentAdminController@salvarObservacaoBoletim');
$router->post('/admin/students/{id}/boletim/observacao/limpar', 'Admin/StudentAdminController@limparObservacaoBoletim');
$router->post('/admin/students/{id}/boletim/observacao/versoes/excluir', 'Admin/StudentAdminController@excluirVersoesObservacaoBoletim');
$router->get('/admin/students/{id}/boletim/pdf', 'Admin/StudentAdminController@gerarBoletimPdf');
$router->post('/admin/students/{id}/boletim/{regraId}/excluir', 'Admin/StudentAdminController@excluirBoletimGerado');
$router->get('/admin/students/{id}/acessar-como', 'Admin/StudentAdminController@acessarComoAluno');
$router->get('/admin/students/{id}/acessar-como-pai', 'Admin/StudentAdminController@acessarComoPai');
$router->get('/admin/students/{id}/edit', 'Admin/StudentAdminController@editarAluno');
$router->post('/admin/students/{id}/foto', 'Admin/StudentAdminController@uploadFotoAluno');
$router->put('/admin/students/{id}', 'Admin/StudentAdminController@atualizarAluno');
$router->post('/admin/students/{id}/excluir', 'Admin/StudentAdminController@excluirAluno');
$router->post('/admin/students/{id}/matricula', 'Admin/StudentAdminController@adicionarMatricula');
$router->post('/admin/students/{id}/matricula-sincronizar-cadastro', 'Admin/StudentAdminController@sincronizarMatriculaComTurmaCadastro');
$router->post('/admin/students/{id}/matricula/{matricula_id}/encerrar', 'Admin/StudentAdminController@encerrarMatricula');
$router->post('/admin/students/{id}/inactivate', 'Admin/StudentAdminController@inactivateAluno');
$router->post('/admin/students/{id}/activate', 'Admin/StudentAdminController@activateAluno');
$router->post('/admin/students/{id}/toggle-pagante', 'Admin/StudentAdminController@togglePaganteAluno');
$router->post('/admin/students/{id}/password', 'Admin/StudentAdminController@updateStudentPassword');
$router->post('/admin/students/{id}/analise-tudinha', 'Admin/StudentAdminController@gerarAnaliseTudinha');
$router->post('/admin/students/responsavel/atualizar', 'Admin/StudentAdminController@atualizarResponsavelAluno');

// Declarações do Aluno (documentos oficiais em PDF)
$router->get('/admin/students/{id}/declaracoes/{tipo}/pdf', 'Declarations/AdminDeclarationController@gerarPdf');

// Histórico Escolar oficial (Fundamental/Médio) — workflow + PDF + validação
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

// Documentos / checklist de entrega do aluno
$router->post('/admin/students/{id}/documentos/salvar', 'Admin/StudentAdminController@salvarDocumentoAluno');
$router->post('/admin/students/{id}/documentos/{docId}/remover', 'Admin/StudentAdminController@removerDocumentoAluno');
$router->get('/admin/students/{id}/documentos/{docId}/baixar', 'Admin/StudentAdminController@baixarDocumentoAluno');
// Reuniões → Modulos/comunicacao/routes.php

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

$router->post('/admin/students/cadastrar-pai', 'Admin/StudentAdminController@cadastrarPai');
$router->post('/admin/students/transfer', 'Admin/StudentAdminController@processarTransferenciaAlunos');
$router->get('/admin/students/remanejamento', 'Admin/StudentAdminController@remanejamentoAlunos');
$router->post('/admin/students/remanejamento', 'Admin/StudentAdminController@processarRemanejamentoAlunos');
$router->get('/admin/students/transferencia-escolar', 'Admin/StudentAdminController@transferenciaEscolarAlunos');
$router->post('/admin/students/transferencia-escolar', 'Admin/StudentAdminController@processarTransferenciaEscolar');

// Gestão de Professores
$router->get('/admin/monitors', 'Admin/MonitorAdminController@monitores');
$router->get('/admin/monitors/{id}/dados', 'Admin/MonitorAdminController@dadosMonitor');
$router->post('/admin/monitors', 'Admin/MonitorAdminController@salvarMonitor');
$router->put('/admin/monitors/{id}', 'Admin/MonitorAdminController@atualizarMonitor');
$router->delete('/admin/monitors/{id}', 'Admin/MonitorAdminController@excluirMonitor');

$router->get('/admin/teachers', 'Admin/TeacherAdminController@professores');
$router->get('/admin/teachers/{id}/dados', 'Admin/TeacherAdminController@dadosProfessor');
$router->post('/admin/teachers', 'Admin/TeacherAdminController@salvarProfessor');
$router->put('/admin/teachers/{id}', 'Admin/TeacherAdminController@atualizarProfessor');
$router->delete('/admin/teachers/{id}', 'Admin/TeacherAdminController@excluirProfessor');
$router->post('/admin/teachers/{id}/toggle-status', 'Admin/TeacherAdminController@toggleStatusProfessor');
$router->post('/admin/teachers/{id}/toggle-pagante', 'Admin/TeacherAdminController@togglePaganteProfessor');
$router->get('/admin/teachers/export-csv', 'Admin/TeacherAdminController@exportarProfessoresCSV');
$router->post('/admin/teachers/import-csv', 'Admin/TeacherAdminController@importarProfessoresCSV');

// Sistema de Jornadas do Admin

// Mural → Modulos/mural/routes.php

// Aulas online → Modulos/aulas-online/routes.php

// EducaInclui → Modulos/educainclui/routes.php

// Gestão de Jornadas (antiga - redireciona para rotas novas) → também em Modulos/jornadas/routes.php
$router->get('/admin/journeys', function() {
    header('Location: ' . URL . '/admin/jornadas');
    exit;
});
$router->get('/admin/journeys/create', function() {
    header('Location: ' . URL . '/admin/jornadas/criar');
    exit;
});
$router->get('/admin/journeys/{id}', function($id) {
    header('Location: ' . URL . '/admin/jornadas/' . $id);
    exit;
});
$router->get('/admin/journeys/{id}/edit', function($id) {
    header('Location: ' . URL . '/admin/jornadas/' . $id);
    exit;
});

// Exercícios → Modulos/exercicios/routes.php

// Gerenciamento de Questões

// Configurações
$router->get('/admin/settings', 'Admin/SchoolSettingsAdminController@configuracoes');
$router->put('/admin/settings', 'User/AdminController@salvarConfiguracoes');
$router->post('/admin/settings/sliders-dashboard', 'Admin/SchoolSettingsAdminController@salvarSlidersDashboard');
// Boletim (config/guia/assistente) → Modulos/boletins/routes.php

// Provas → Modulos/provas/routes.php

// MCP / consulta de provas na visão do professor (somente leitura)

$router->post('/admin/consulta-jornadas-aluno/mcp/ferramenta', 'Admin/JornadasAlunoMcpController@ferramenta');

// MCP / turma, bloco, jornadas professor, boletim e faltas (somente leitura)
$router->post('/admin/consulta-assistente/mcp/ferramenta', 'Admin/AssistenteConsultaMcpController@ferramenta');

// Assistente (chat IA + histórico — coordenação)
// Alias legado

// Wiki visual dos Markdowns em doc_sistema/
$router->get('/admin/doc-sistema', 'Admin/DocSistemaController@index');
$router->get('/admin/doc-sistema/{pagina}', 'Admin/DocSistemaController@index');

// Faltas (Admin/Coordenação)
$router->get('/admin/faltas', 'Admin/SchoolAbsenceController@index');
$router->get('/admin/frequencia', 'Admin/FrequenciaAdminController@index');
$router->get('/admin/faltas/exportar-excel', 'Admin/SchoolAbsenceController@exportarExcel');
$router->get('/admin/faltas/lancar', 'Admin/SchoolAbsenceController@lancar');
$router->get('/admin/faltas/lancar/exportar-excel', 'Admin/SchoolAbsenceController@exportarLancamentoExcel');
$router->post('/admin/faltas/criar', 'Admin/SchoolAbsenceController@criarEvento');
$router->post('/admin/faltas/atualizar', 'Admin/SchoolAbsenceController@atualizarEvento');
$router->post('/admin/faltas/salvar', 'Admin/SchoolAbsenceController@salvarLancamentos');
$router->post('/admin/faltas/excluir', 'Admin/SchoolAbsenceController@excluirEvento');

// Diário de Classe: rotas em app/Modulos/diario/routes.php

// Conformidade → Modulos/conformidade/routes.php

// BNCC + Plano de Curso
$router->get('/admin/bncc', 'Admin/BnccController@index');
$router->post('/admin/bncc/importar', 'Admin/BnccController@importar');
$router->get('/admin/plano-curso', 'Admin/CoursePlanController@index');
$router->get('/admin/plano-curso/form', 'Admin/CoursePlanController@form');
$router->post('/admin/plano-curso/salvar', 'Admin/CoursePlanController@salvar');
$router->post('/admin/plano-curso/excluir', 'Admin/CoursePlanController@excluir');
$router->post('/admin/plano-curso/marcar-trabalhada', 'Admin/CoursePlanController@marcarTrabalhada');

// Calendário Letivo (dias letivos / carga horária anual)
$router->get('/admin/calendario-letivo', 'Admin/SchoolCalendarController@index');
$router->post('/admin/calendario-letivo/salvar-ano', 'Admin/SchoolCalendarController@salvarAno');
$router->post('/admin/calendario-letivo/excluir', 'Admin/SchoolCalendarController@excluirCalendario');
$router->post('/admin/calendario-letivo/salvar-evento', 'Admin/SchoolCalendarController@salvarEvento');
$router->post('/admin/calendario-letivo/excluir-evento', 'Admin/SchoolCalendarController@excluirEvento');
$router->post('/admin/calendario-letivo/salvar-tipo', 'Admin/SchoolCalendarController@salvarTipo');
$router->post('/admin/calendario-letivo/excluir-tipo', 'Admin/SchoolCalendarController@excluirTipo');
$router->post('/admin/calendario-letivo/efeito-tipo', 'Admin/SchoolCalendarController@atualizarEfeitoTipo');

// Documentos Institucionais (PPP, Regimento) + Documentos do Professor
$router->get('/admin/documentos-institucionais', 'Admin/InstitutionalDocsController@index');
$router->post('/admin/documentos-institucionais/salvar', 'Admin/InstitutionalDocsController@salvar');
$router->post('/admin/documentos-institucionais/excluir', 'Admin/InstitutionalDocsController@excluir');
$router->get('/admin/documentos-institucionais/baixar', 'Admin/InstitutionalDocsController@baixar');
$router->get('/admin/teachers-documentos', 'Admin/TeacherDocsController@index');
$router->post('/admin/teachers-documentos/salvar', 'Admin/TeacherDocsController@salvar');

// Saúde Acadêmica (Secretaria / Coordenação — read-only)
$router->get('/admin/saude-academica', 'Admin/SaudeAcademicaController@index');

// UI Modelos (design system demo — perfil dev)
$router->get('/admin/configuracao/ui-modelos', 'Admin/UiModelosController@index');
$router->get('/admin/configuracao/ui-modelos/botoes', 'Admin/UiModelosController@botoes');
$router->get('/admin/configuracao/ui-modelos/tabela', 'Admin/UiModelosController@tabela');
$router->get('/admin/configuracao/ui-modelos/formulario', 'Admin/UiModelosController@formulario');
$router->post('/admin/configuracao/ui-modelos/formulario', 'Admin/UiModelosController@formularioEnviar');
$router->get('/admin/configuracao/ui-modelos/offcanvas', 'Admin/UiModelosController@offcanvas');
$router->get('/admin/configuracao/ui-modelos/badges', 'Admin/UiModelosController@badges');
$router->get('/admin/configuracao/ui-modelos/wizard', 'Admin/UiModelosController@wizard');
$router->post('/admin/configuracao/ui-modelos/wizard', 'Admin/UiModelosController@wizardEnviar');

// Configurações Avançadas (Dev)
$router->get('/admin/dev', 'Admin/DevAdminController@dev');
$router->get('/admin/dev/integracoes', 'Admin/DevAdminController@devIntegracoes');
$router->get('/admin/dev/modulos', 'Admin/DevAdminController@devModulos');
$router->get('/admin/dev/aparencia', 'Admin/DevAdminController@devAparencia');
$router->get('/admin/dev/ia', 'Admin/DevAdminController@devIa');
$router->get('/admin/dev/conteudo', 'Admin/DevAdminController@devConteudo');
$router->get('/admin/dev/operacao', 'Admin/DevAdminController@devOperacao');
$router->get('/admin/dev-settings', 'Admin/DevSettingsController@index');
$router->post('/admin/dev-settings/save', 'Admin/DevSettingsController@save');
$router->get('/admin/dev-settings/pwa', 'Admin/DevAdminController@devPwaSettings');
$router->get('/admin/dev-settings/layout-mobile', 'Admin/DevAdminController@devLayoutMobileSettings');
$router->get('/admin/dev-settings/custos-llm', 'Admin/DevAdminController@devCustosLLM');
$router->post('/admin/dev-settings/custos-llm/importar-banco', 'Admin/DevAdminController@devCustosLLMBackfill');
$router->post('/admin/dev-settings/custos-llm/processar-data', 'Admin/DevAdminController@devCustosLLMProcessarData');
$router->get('/admin/dev-settings/logs', 'Admin/DevAdminController@devLogs');
$router->post('/admin/dev-settings/logs/delete', 'Admin/DevAdminController@devLogsDelete');
$router->get('/admin/dev-settings/logins', 'Admin/DevAdminController@devLogins');
$router->post('/admin/dev-settings/whatsapp-evolution-test', 'Admin/DevAdminController@devWhatsappEvolutionTest');
$router->get('/admin/dev/tickets', 'Admin/DevAdminController@devTickets');
$router->get('/admin/dev/tickets/{id}', 'Admin/DevAdminController@devTicketShow');
$router->post('/admin/dev/tickets/{id}/reply', 'Admin/DevAdminController@devTicketReply');
$router->post('/admin/dev/tickets/{id}/close', 'Admin/DevAdminController@devTicketClose');
$router->post('/admin/dev/modules', 'Admin/SchoolSettingsAdminController@salvarModulos');
$router->post('/admin/dev/menu-order', 'Admin/SchoolSettingsAdminController@salvarMenuOrder');
$router->post('/admin/dev/menu-links', 'User/AdminController@salvarMenuLinks');
$router->get('/admin/dev/prompts-redacao', 'Admin/DevAdminController@buscarPromptsRedacao');
$router->post('/admin/dev/prompts-redacao/save', 'Admin/DevAdminController@salvarPromptsRedacao');

// Limites Diários (Dev)
$router->post('/admin/dev/limites-diarios/save', 'Admin/DevAdminController@salvarLimitesDiarios');
$router->post('/admin/dev/valor-usuario/save', 'Admin/DevAdminController@salvarValorUsuario');
// Dev financeiro → Modulos/financeiro/routes.php

// API Keys (Dev)
$router->post('/admin/dev/api-keys/save', 'Admin/DevAdminController@salvarApiKeys');

// Email Config (Dev)
$router->post('/admin/dev/email/save', 'Admin/SchoolSettingsAdminController@salvarEmailConfig');

// Tutoriais (Dev - Admin)
$router->get('/admin/dev/tutoriais', 'Admin/DevAdminController@listarTutoriais');
$router->post('/admin/dev/tutoriais/save', 'Admin/DevAdminController@salvarTutorial');
$router->post('/admin/dev/tutoriais/delete', 'Admin/DevAdminController@deletarTutorial');

// Tutoriais (Público - para professores)
$router->get('/tutoriais', 'Admin/DevAdminController@listarTutoriais');

// Avatares dos alunos
$router->get('/admin/avatares-alunos', 'Admin/AvatarAlunoAdminController@index');
$router->post('/admin/avatares-alunos/upload', 'Admin/AvatarAlunoAdminController@upload');
$router->post('/admin/avatares-alunos/excluir', 'Admin/AvatarAlunoAdminController@excluir');
$router->post('/admin/avatares-alunos/excluir-selecionados', 'Admin/AvatarAlunoAdminController@excluirSelecionados');
$router->post('/admin/avatares-alunos/excluir-todos', 'Admin/AvatarAlunoAdminController@excluirTodos');
// Hub avaliações
$router->get('/admin/avaliacoes',      'Education/AnoLetivoController@avaliacoes');
// AVA → Modulos/ava/routes.php

// Layout do Sistema (Dev Settings)
$router->get('/admin/dev/layout', 'Admin/SchoolSettingsAdminController@layout');
$router->post('/admin/dev/layout/save', 'Admin/SchoolSettingsAdminController@saveLayout');
$router->post('/admin/dev/layout/upload', 'Admin/SchoolSettingsAdminController@uploadImage');

// Migrations (Dev Settings)
$router->get('/admin/dev/migrations', 'Admin/DevAdminController@migrations');
$router->post('/admin/dev/migrations/escola/salvar', 'Admin/DevAdminController@salvarEscolaDatabase');
$router->post('/admin/dev/migrations/escola/deletar', 'Admin/DevAdminController@deletarEscolaDatabase');
$router->post('/admin/dev/migrations/executar', 'Admin/DevAdminController@executarMigration');
$router->post('/admin/dev/migrations/executar-todas', 'Admin/DevAdminController@executarTodasMigrations');

// SSH e Git (Dev Settings - apenas demo.educatudo.com)
$router->get('/admin/dev/ssh', 'Admin/DevAdminController@ssh');
$router->post('/admin/dev/ssh/executar', 'Admin/DevAdminController@executarSSH');

// Monitoramento
$router->get('/admin/dev/metrics', 'Admin/DevAdminController@getMetrics');
$router->post('/admin/dev/send-metrics', 'Admin/DevAdminController@sendMetrics');

// CRUD de Usuários
$router->get('/admin/usuarios', 'User/UserController@index');
$router->get('/admin/usuarios/{id}/dados', 'User/UserController@dados');
$router->post('/admin/usuarios', 'User/UserController@store');
$router->post('/admin/usuarios/{id}', 'User/UserController@update');
$router->post('/admin/usuarios/{id}/avatar', 'User/UserController@uploadAvatar');
$router->post('/admin/usuarios/{id}/senha', 'User/UserController@changePassword');
$router->post('/admin/usuarios/{id}/excluir', 'User/UserController@excluir');
$router->post('/admin/usuarios/{id}/restaurar', 'User/UserController@restaurar');

// Perfis de Permissão (Admin escola)
$router->get('/admin/permissoes-perfis', 'User/AdminPermissionProfileController@index');
$router->get('/admin/permissoes-perfis/{id}/dados', 'User/AdminPermissionProfileController@dados');

// Financeiro escolar → Modulos/financeiro/routes.php
$router->post('/admin/permissoes-perfis', 'User/AdminPermissionProfileController@store');
$router->post('/admin/permissoes-perfis/{id}', 'User/AdminPermissionProfileController@update');

// Webhooks (Dev Settings)
$router->get('/admin/dev/webhooks', 'Integrations/WebhookController@index');
$router->post('/admin/dev/webhooks', 'Integrations/WebhookController@create');
$router->put('/admin/dev/webhooks/{id}', 'Integrations/WebhookController@update');
$router->delete('/admin/dev/webhooks/{id}', 'Integrations/WebhookController@delete');
$router->post('/admin/dev/webhooks/{id}/test', 'Integrations/WebhookController@test');

// Gestão de Matérias
$router->get('/admin/componentes-curriculares', 'Admin/ComponenteCurricularAdminController@index');
$router->get('/admin/componentes-curriculares/{id}/dados', 'Admin/ComponenteCurricularAdminController@dados');
$router->post('/admin/componentes-curriculares', 'Admin/ComponenteCurricularAdminController@store');
$router->put('/admin/componentes-curriculares/{id}', 'Admin/ComponenteCurricularAdminController@update');
$router->delete('/admin/componentes-curriculares/{id}', 'Admin/ComponenteCurricularAdminController@delete');

// Salas / Ambientes (CRUD - reaproveita school_locations, usada também pelo Patrimônio)
$router->get('/admin/salas', 'Admin/SalaAdminController@index');
$router->get('/admin/salas/{id}/dados', 'Admin/SalaAdminController@dados');
$router->post('/admin/salas', 'Admin/SalaAdminController@store');
$router->put('/admin/salas/{id}', 'Admin/SalaAdminController@update');
$router->delete('/admin/salas/{id}', 'Admin/SalaAdminController@delete');

// Unidades da Escola (matriz/filial + dados institucionais)
$router->get('/admin/unidades', 'Admin/SchoolUnitsAdminController@index');
$router->get('/admin/unidades/{id}/dados', 'Admin/SchoolUnitsAdminController@dados');
$router->post('/admin/unidades', 'Admin/SchoolUnitsAdminController@store');
$router->put('/admin/unidades/{id}', 'Admin/SchoolUnitsAdminController@update');
$router->delete('/admin/unidades/{id}', 'Admin/SchoolUnitsAdminController@delete');

// Patrimônio / almoxarifado → Modulos/patrimonio/routes.php

// Gestão de Turmas (CRUD Completo - criar/editar em offcanvas no index; show continua página própria)
$router->get('/admin/turmas', 'Education/ClassController@index');
$router->post('/admin/turmas', 'Education/ClassController@store');
$router->get('/admin/turmas/{id}', 'Education/ClassController@show');
$router->get('/admin/turmas/{id}/dados', 'Education/ClassController@dados');
$router->get('/admin/turmas/{id}/buscar-alunos', 'Education/ClassController@buscarAlunosParaVincular');
$router->post('/admin/turmas/{id}/vincular-aluno', 'Education/ClassController@vincularAluno');
$router->post('/admin/turmas/{id}', 'Education/ClassController@update');
$router->delete('/admin/turmas/{id}', 'Education/ClassController@destroy');
$router->post('/admin/turmas/bulk-delete', 'Education/ClassController@bulkDestroy');
$router->post('/admin/turmas/{id}/toggle-status', 'Education/ClassController@toggleStatus');
$router->get('/admin/turmas/by-ano-letivo', 'Education/ClassController@getByAnoLetivo');
$router->get('/admin/turmas/by-serie', 'Education/ClassController@getBySerie');
$router->get('/admin/turmas/{id}/lista-chamada', 'Admin/StudentAdminController@listaChamadaTurma');
$router->post('/admin/turmas/{id}/lista-chamada/config', 'Admin/StudentAdminController@salvarListaChamadaConfig');
$router->post('/admin/turmas/{id}/lista-chamada/recalcular', 'Admin/StudentAdminController@recalcularListaChamada');
$router->get('/admin/turmas/{id}/lista-chamada/exportar', 'Admin/StudentAdminController@listaChamadaExportar');
$router->get('/admin/turmas/{id}/lista-chamada/pdf', 'Admin/StudentAdminController@listaChamadaPdf');
$router->get('/admin/turmas/{id}/export-alunos-csv', 'Education/ClassController@exportarAlunosCsv');

// Hubs do menu principal — landing pages
$router->get('/admin/academico',       'Education/AnoLetivoController@academico');
$router->get('/admin/pedagogico',      'Education/AnoLetivoController@pedagogico');
$router->get('/admin/rotina',          'Education/AnoLetivoController@rotina');
$router->get('/admin/paineis',         'Education/AnoLetivoController@paineis');
$router->get('/admin/gestao-escolar',  'Education/AnoLetivoController@gestaoEscolar');
// Hub Comunicação → Modulos/comunicacao/routes.php
$router->get('/admin/conteudo',            'Education/AnoLetivoController@conteudo');
$router->get('/admin/financeiro-escolar',  'Education/AnoLetivoController@financeiroEscolar');
$router->get('/admin/monitoramento-escolar', 'Education/AnoLetivoController@monitoramento');
$router->get('/admin/relatorios',          'Education/AnoLetivoController@relatorios');
$router->get('/admin/sistema',             'Education/AnoLetivoController@sistema');
$router->get('/admin/gestao-usuarios',     'Education/AnoLetivoController@gestaoUsuarios');
$router->get('/admin/z-configuracao',      'Education/AnoLetivoController@zConfiguracao');
$router->get('/admin/z-configuracao/zerar-popular', 'Education/AnoLetivoController@zerarPopularEduca');
$router->post('/admin/z-configuracao/zerar-popular', 'Education/AnoLetivoController@zerarPopularEducaExecutar');

// Ano Letivo (CRUD - estrutura normalizada, offcanvas em index)
$router->get('/admin/ano-letivo', 'Education/AnoLetivoController@index');
$router->get('/admin/ano-letivo/{id}/dados', 'Education/AnoLetivoController@dados');
$router->post('/admin/ano-letivo', 'Education/AnoLetivoController@store');
$router->post('/admin/ano-letivo/{id}/update', 'Education/AnoLetivoController@update');
$router->post('/admin/ano-letivo/{id}/delete', 'Education/AnoLetivoController@destroy');

// Curso (CRUD - tabela curso, estrutura normalizada, offcanvas em index)
$router->get('/admin/cursos-series', 'Education/CursoController@cursosSeries');
$router->get('/admin/curso', 'Education/CursoController@index');
$router->get('/admin/curso/{id}/dados', 'Education/CursoController@dados');
$router->post('/admin/curso', 'Education/CursoController@store');
$router->post('/admin/curso/{id}/update', 'Education/CursoController@update');
$router->post('/admin/curso/{id}/delete', 'Education/CursoController@destroy');
$router->post('/admin/curso/bulk-delete', 'Education/CursoController@bulkDestroy');
$router->get('/admin/curso/{id}/importar-alunos', 'Education/CursoController@importarAlunos');
$router->post('/admin/curso/{id}/importar-alunos/processar', 'Education/CursoController@processarImportacaoAlunos');
$router->get('/admin/curso/{id}/modelo-csv', 'Education/CursoController@modeloCsv');

// Série (CRUD - depende de curso, offcanvas em index)
$router->get('/admin/serie', 'Education/SerieController@index');
$router->get('/admin/serie/{id}/dados', 'Education/SerieController@dados');
$router->post('/admin/serie', 'Education/SerieController@store');
$router->post('/admin/serie/{id}/update', 'Education/SerieController@update');
$router->post('/admin/serie/{id}/delete', 'Education/SerieController@destroy');
$router->post('/admin/serie/bulk-delete', 'Education/SerieController@bulkDestroy');

// Matriz Curricular (CRUD - série × Componente Curricular × carga horária)
$router->get('/admin/matrizes-curriculares', 'Admin/MatrizCurricularAdminController@index');
$router->get('/admin/matrizes-curriculares/{id}/dados', 'Admin/MatrizCurricularAdminController@dados');
$router->post('/admin/matrizes-curriculares', 'Admin/MatrizCurricularAdminController@store');
$router->post('/admin/matrizes-curriculares/{id}/update', 'Admin/MatrizCurricularAdminController@update');
$router->post('/admin/matrizes-curriculares/{id}/delete', 'Admin/MatrizCurricularAdminController@delete');

// Regras Acadêmicas: rotas em app/Modulos/regras-academicas/routes.php

// Gestão de Tipos de Curso e Cursos (legado)
$router->get('/admin/cursos', 'Education/CourseCatalogController@index');
$router->post('/admin/cursos/tipos', 'Education/CourseCatalogController@storeTipoCurso');
$router->post('/admin/cursos', 'Education/CourseCatalogController@storeCurso');

// Grade Horária de Aulas
$router->get('/admin/grade-horaria', 'Education/GradeHorariaController@index');
$router->get('/admin/grade-horaria/pdf', 'Education/GradeHorariaController@pdf');
$router->post('/admin/grade-horaria', 'Education/GradeHorariaController@store');
$router->get('/admin/grade-horaria/{id}/dados', 'Education/GradeHorariaController@dados');
$router->post('/admin/grade-horaria/{id}', 'Education/GradeHorariaController@update');
$router->delete('/admin/grade-horaria/{id}', 'Education/GradeHorariaController@destroy');
$router->post('/admin/grade-horaria/processar-imagem-ia', 'Education/GradeHorariaController@processarImagemIA');
$router->post('/admin/grade-horaria/salvar-importacao-ia', 'Education/GradeHorariaController@salvarImportacaoIA');

// Relatórios Administrativos
$router->get('/admin/reports', 'Admin/ReportAdminController@relatorios');
$router->get('/admin/reports/censo', 'Admin/ReportAdminController@censo');
$router->get('/admin/reports/boletim-coordenacao', 'Admin/ReportAdminController@boletimCoordenacao');
$router->post('/admin/reports/boletim-coordenacao', 'Admin/ReportAdminController@prepararBoletimCoordenacao');
$router->post('/admin/reports/boletim-coordenacao/observacoes/limpar', 'Admin/ReportAdminController@limparObservacoesBoletimCoordenacao');
$router->get('/admin/reports/boletim-coordenacao/buscar-alunos', 'Admin/ReportAdminController@buscarAlunosBoletimCoordenacao');
$router->get('/admin/reports/boletim-coordenacao/exportar', 'Admin/ReportAdminController@exportarBoletimCoordenacao');
$router->get('/admin/reports/boletim-coordenacao/zip/{id}', 'Admin/ReportAdminController@baixarZipBoletinsVidaEscolar');

// Financeiro
// Legado: redireciona /admin/financeiro (SaaS billing) → novo módulo escolar
$router->get('/admin/financeiro', function() { header('Location: ' . URL . '/admin/finance'); exit; });
$router->get('/admin/financeiro/relatorio-pagantes', function() { header('Location: ' . URL . '/admin/finance/report/inadimplencia'); exit; });

// Minicursos → Modulos/minicursos/routes.php


// Planos de Aula (Admin)
$router->get('/admin/planos-aula', 'Education/LessonPlanController@indexAdmin');
$router->get('/admin/planos-aula/visualizar/{id}', 'Education/LessonPlanController@visualizar');
$router->get('/admin/planos-aula/editar/{id}', 'Education/LessonPlanController@editar');
$router->post('/admin/planos-aula/atualizar/{id}', 'Education/LessonPlanController@atualizar');
$router->post('/admin/planos-aula/aprovar-rejeitar/{id}', 'Education/LessonPlanController@aprovarRejeitar');
$router->post('/admin/planos-aula/aprovar-rascunhos', 'Education/LessonPlanController@aprovarTodosRascunhosAdmin');
$router->get('/admin/planos-aula/pdf/{id}', 'Education/LessonPlanController@exportarPdf');

// Provas Online (Admin)

// Blocos de Provas (Admin/Coordenador/Diretor)
// redirect /admin/provas/blocos → Modulos/provas/routes.php
// Lote (antes das rotas com {id})


// Blocos Modelo (Templates)

// Tipos de Avaliação (Provas Online)

// Provas de Professores

// Alunos Online
$router->get('/admin/alunos-online', 'Admin/MonitorAdminController@alunosOnline');
$router->get('/admin/api/alunos-online', 'Admin/MonitorAdminController@apiAlunosOnline');
$router->get('/admin/api/alunos-online/stream', 'Admin/MonitorAdminController@apiAlunosOnlineStream');
$router->get('/professor/api/alunos-online', 'User/TeacherController@apiAlunosOnline');
$router->get('/professor/api/alunos-online/stream', 'User/TeacherController@apiAlunosOnlineStream');
$router->get('/admin/reports/api/conversas', 'Admin/ReportAdminController@apiConversasAluno');
$router->get('/admin/reports/api/mensagens', 'Admin/ReportAdminController@apiMensagensConversa');
$router->get('/admin/reports/api/exercicios', 'Admin/ReportAdminController@apiExerciciosAluno');
$router->get('/admin/reports/api/redacoes', 'Admin/ReportAdminController@apiRedacoesAluno');
$router->get('/admin/reports/school', 'User/AdminController@relatorioEscola');
$router->get('/admin/reports/classes', 'User/AdminController@relatorioTurmas');
$router->get('/admin/reports/teachers', 'User/AdminController@relatorioProfessores');

// AVA / EAD (Ambiente Virtual de Aprendizagem)
// Categorias (página própria — um cadastro por tela)
// Cursos (rotas estáticas/compostas antes das com {id})
// Disciplinas
// Avaliações da disciplina (vínculo com prova — um cadastro por tela)
// Módulos
// Aulas
// Anexos

// Matrículas → Modulos/matricula/routes.php
