<?php
// Rotas para Administração (perfil: admin_escola)
$router->get('/admin/jornadas/ai-job/{id}/status', 'AIJobController@status');
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
// Arquivos: rotas em app/Modulos/arquivos/routes.php
$router->get('/admin/apostilas', 'Apostilas/AdminApostilaController@index');
$router->post('/admin/apostilas/upload', 'Apostilas/AdminApostilaController@upload');
$router->post('/admin/apostilas/editar', 'Apostilas/AdminApostilaController@update');
$router->post('/admin/apostilas/excluir', 'Apostilas/AdminApostilaController@delete');

// IA da Apostila (módulo novo, independente do módulo Apostilas acima)
$router->get('/admin/apostilas-ia', 'Admin/ApostilaIaAdminController@index');
$router->get('/admin/apostilas-ia/criar', 'Admin/ApostilaIaAdminController@criar');
$router->post('/admin/apostilas-ia/upload', 'Admin/ApostilaIaAdminController@upload');
$router->get('/admin/apostilas-ia/{id}/editar', 'Admin/ApostilaIaAdminController@editar');
$router->post('/admin/apostilas-ia/{id}/atualizar', 'Admin/ApostilaIaAdminController@atualizar');
$router->post('/admin/apostilas-ia/{id}/reprocessar', 'Admin/ApostilaIaAdminController@reprocessar');
$router->post('/admin/apostilas-ia/{id}/enviar-pdf', 'Admin/ApostilaIaAdminController@enviarPdf');
$router->post('/admin/apostilas-ia/{id}/enviar-capa', 'Admin/ApostilaIaAdminController@enviarCapa');
$router->get('/admin/apostilas-ia/{id}/capa', 'Admin/ApostilaIaAdminController@capa');
$router->get('/admin/apostilas-ia/{id}/pdf', 'Admin/ApostilaIaAdminController@pdf');
$router->get('/admin/apostilas-ia/{id}/status', 'Admin/ApostilaIaAdminController@status');
$router->get('/admin/maintenance/painel', 'Admin/StudentAdminController@painelManutencao');
$router->post('/admin/maintenance/toggle', 'Admin/StudentAdminController@toggleMaintenance');
$router->get('/admin/monitoramento/alertas', 'Admin/OccurrenceAdminController@alertasSensiveis');
$router->post('/admin/monitoramento/alertas/atualizar', 'Admin/OccurrenceAdminController@atualizarAlertaSensivel');
$router->post('/admin/monitoramento/alertas/ver-conteudo', 'Admin/OccurrenceAdminController@verConteudoAlertaSensivel');

// Redação Configurável (Admin)
$router->get('/admin/redacao-configuravel', 'Essays/AdminEssayController@index');
$router->get('/admin/redacao-configuravel/bancas/novo', 'Essays/AdminEssayController@boardCreate');
$router->post('/admin/redacao-configuravel/bancas', 'Essays/AdminEssayController@boardStore');
$router->get('/admin/redacao-configuravel/bancas/{id}/editar', 'Essays/AdminEssayController@boardEdit');
$router->put('/admin/redacao-configuravel/bancas/{id}', 'Essays/AdminEssayController@boardUpdate');
$router->post('/admin/redacao-configuravel/bancas/{id}', 'Essays/AdminEssayController@boardUpdate');
$router->delete('/admin/redacao-configuravel/bancas/{id}', 'Essays/AdminEssayController@boardDelete');
$router->post('/admin/redacao-configuravel/bancas/{id}/toggle-status', 'Essays/AdminEssayController@boardToggleStatus');
$router->get('/admin/redacao-configuravel/bancas/{boardId}/tipos', 'Essays/AdminEssayController@textTypesIndex');
$router->get('/admin/redacao-configuravel/bancas/{boardId}/tipos/novo', 'Essays/AdminEssayController@textTypeCreate');
$router->post('/admin/redacao-configuravel/bancas/{boardId}/tipos', 'Essays/AdminEssayController@textTypeStore');
$router->get('/admin/redacao-configuravel/bancas/{boardId}/tipos/{id}/editar', 'Essays/AdminEssayController@textTypeEdit');
$router->put('/admin/redacao-configuravel/bancas/{boardId}/tipos/{id}', 'Essays/AdminEssayController@textTypeUpdate');
$router->post('/admin/redacao-configuravel/bancas/{boardId}/tipos/{id}', 'Essays/AdminEssayController@textTypeUpdate');
$router->delete('/admin/redacao-configuravel/bancas/{boardId}/tipos/{id}', 'Essays/AdminEssayController@textTypeDelete');
$router->get('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/criterios', 'Essays/AdminEssayController@criteriaIndex');
$router->get('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/criterios/novo', 'Essays/AdminEssayController@criterionCreate');
$router->post('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/criterios', 'Essays/AdminEssayController@criterionStore');
$router->get('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/criterios/{id}/editar', 'Essays/AdminEssayController@criterionEdit');
$router->put('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/criterios/{id}', 'Essays/AdminEssayController@criterionUpdate');
$router->post('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/criterios/{id}', 'Essays/AdminEssayController@criterionUpdate');
$router->delete('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/criterios/{id}', 'Essays/AdminEssayController@criterionDelete');
$router->get('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/prompts', 'Essays/AdminEssayController@promptsIndex');
$router->get('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/prompts/novo', 'Essays/AdminEssayController@promptCreate');
$router->post('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/prompts', 'Essays/AdminEssayController@promptStore');
$router->get('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/prompts/{id}/editar', 'Essays/AdminEssayController@promptEdit');
$router->put('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/prompts/{id}', 'Essays/AdminEssayController@promptUpdate');
$router->post('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/prompts/{id}', 'Essays/AdminEssayController@promptUpdate');
$router->delete('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/prompts/{id}', 'Essays/AdminEssayController@promptDelete');
$router->post('/admin/redacao-configuravel/bancas/{boardId}/tipos/{textTypeId}/prompts/{id}/ativar', 'Essays/AdminEssayController@promptSetActive');
$router->get('/admin/redacao-professor', 'Essays/AdminEssayController@proposalsIndex');
$router->get('/admin/redacao-professor/relatorio', 'Essays/AdminEssayController@report');
$router->get('/admin/redacao-professor/analytics', 'Essays/AdminEssayAnalyticsController@index');
$router->get('/admin/redacao-professor/analytics/aluno', 'Essays/AdminEssayAnalyticsController@byStudent');
$router->get('/admin/redacao-professor/analytics/proposta', 'Essays/AdminEssayAnalyticsController@byProposal');
$router->get('/admin/redacao-professor/analytics/exportar', 'Essays/AdminEssayAnalyticsController@exportExcel');
$router->get('/admin/redacao-professor/novo', 'Essays/AdminEssayController@proposalCreate');
$router->post('/admin/redacao-professor', 'Essays/AdminEssayController@proposalStore');
$router->get('/admin/redacao-professor/api/tipos-textuais/{boardId}', 'Essays/AdminEssayController@apiTextTypes');
$router->get('/admin/redacao-professor/api/alunos-by-turmas', 'Essays/AdminEssayController@apiAlunosByTurmas');
$router->post('/admin/redacao-professor/upload-imagem', 'Essays/AdminEssayController@uploadImage');
$router->post('/admin/redacao-professor/upload-tema-pronto', 'Essays/AdminEssayController@uploadTemaPronto');
$router->post('/admin/redacao-professor/gerar-repertorio-ia', 'Essays/AdminEssayController@gerarRepertorioIA');
$router->get('/admin/redacao-professor/propostas/{proposalId}/envios/{submissionId}', 'Essays/AdminEssayController@submissionDetail');
$router->get('/admin/redacao-configuravel/propostas/{id}', 'Essays/AdminEssayController@proposalShow');
$router->post('/admin/redacao-configuravel/propostas/{id}/permissoes/adicionar', 'Essays/AdminEssayController@grantProfessorPermission');
$router->post('/admin/redacao-configuravel/propostas/{id}/permissoes/remover', 'Essays/AdminEssayController@revokeProfessorPermission');
$router->post('/admin/redacao-professor/{id}/toggle-status', 'Essays/AdminEssayController@proposalToggleStatus');

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
// Reuniões (ATA com pais + reuniões gerais)
$router->get('/admin/reunioes/aluno',           'Admin/MeetingController@alunoIndex');
$router->post('/admin/reunioes/aluno/salvar',   'Admin/MeetingController@alunoSalvar');
$router->post('/admin/reunioes/aluno/excluir',  'Admin/MeetingController@alunoExcluir');
$router->get('/admin/reunioes/geral',           'Admin/MeetingController@geralIndex');
$router->post('/admin/reunioes/geral/salvar',   'Admin/MeetingController@geralSalvar');
$router->post('/admin/reunioes/geral/excluir',  'Admin/MeetingController@geralExcluir');

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

$router->post('/admin/ocorrencias/transcrever-audio', 'Admin/OccurrenceAdminController@transcreverOcorrenciaAudioGeral');
$router->post('/admin/ocorrencias/auto-preencher', 'Admin/OccurrenceAdminController@autoPreencherOcorrenciaGeral');
$router->get('/admin/tentativas-login', 'Admin/OccurrenceAdminController@tentativasLoginIndex');
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
$router->get('/admin/jornadas', 'Education/AdminJourneyController@index');
$router->get('/admin/jornadas/criar', 'Education/AdminJourneyController@criar');
$router->get('/admin/jornadas/relatorio', 'Education/AdminJourneyController@relatorio');
$router->post('/admin/jornadas', 'Education/AdminJourneyController@salvar');
$router->get('/admin/jornadas/{id}', 'Education/AdminJourneyController@show');
$router->get('/admin/jornadas/{id}/exercicios-alunos', 'Education/AdminJourneyController@exerciciosAlunos');
$router->get('/admin/jornadas/{id}/resumos/{resumo_id}', 'Education/AdminJourneyController@verResumo');
$router->get('/admin/jornadas/{jornada_id}/aluno/{aluno_id}/exercicios', 'Education/AdminJourneyController@verExerciciosAluno');
$router->get('/admin/jornadas/{id}/editar', 'Education/AdminJourneyController@editar');
$router->post('/admin/jornadas/{id}/atualizar', 'Education/AdminJourneyController@atualizar');
$router->post('/admin/jornadas/excluir', 'Education/AdminJourneyController@excluir');
$router->post('/admin/jornadas/inativar', 'Education/AdminJourneyController@inativar');
$router->post('/admin/jornadas/inativar-lote', 'Education/AdminJourneyController@inativarLote');
$router->post('/admin/jornadas/resumos/atribuir-nota', 'Education/AdminJourneyController@atribuirNotaResumo');
$router->post('/admin/jornadas/exercicios/atribuir-nota-dissertativa', 'Education/AdminJourneyController@atribuirNotaDissertativa');
$router->delete('/admin/jornadas/{id}', 'Education/AdminJourneyController@delete');
$router->get('/admin/jornadas/professor/{professor_id}', 'Education/AdminJourneyController@porProfessor');
$router->get('/admin/jornadas/turma/{turma_id}', 'Education/AdminJourneyController@porTurma');
$router->post('/admin/jornadas/toggle-status', 'Education/AdminJourneyController@toggleStatus');
$router->get('/admin/jornadas/retomar', 'Education/AdminJourneyController@retomarJornada');
$router->get('/admin/jornadas/turmas-do-professor', 'Education/AdminJourneyController@turmasDoProfessor');
$router->get('/admin/jornadas/buscar-alunos-criar', 'Education/AdminJourneyController@buscarAlunosCriar');
$router->get('/admin/jornadas/buscar-alunos-professor', 'Education/AdminJourneyController@buscarAlunosProfessor');
$router->post('/admin/jornadas/retomar-aluno', 'Education/AdminJourneyController@retomarJornadaAluno');
$router->get('/admin/jornadas/{id}/modulos', 'Education/AdminJourneyController@gerenciarModulos');
$router->get('/admin/jornadas/{id}/modulos/lista', 'Education/AdminJourneyController@listarModulos');
$router->post('/admin/jornadas/adicionar-modulo', 'Education/AdminJourneyController@adicionarModulo');
$router->post('/admin/jornadas/remover-modulo', 'Education/AdminJourneyController@removerModulo');
$router->post('/admin/jornadas/atualizar-ordem-modulos', 'Education/AdminJourneyController@atualizarOrdemModulos');
$router->get('/admin/jornadas/modulos/{modulo_id}/exercicios', 'Education/AdminJourneyController@gerenciarExerciciosModulo');
$router->post('/admin/jornadas/modulos/adicionar-exercicio', 'Education/AdminJourneyController@adicionarExercicioModulo');
$router->post('/admin/jornadas/modulos/gerar-exercicio-ia', 'Education/AdminJourneyController@gerarExercicioIAModulo');
$router->post('/admin/jornadas/modulos/importar-exercicios-ia/{jobId}', 'Education/AdminJourneyController@importarExerciciosModuloIA');
$router->post('/admin/jornadas/modulos/remover-exercicio', 'Education/AdminJourneyController@removerExercicioModulo');
$router->post('/admin/jornadas/modulos/alternar-status-exercicio', 'Education/AdminJourneyController@alternarStatusExercicio');
$router->get('/admin/jornadas/modulos/buscar-exercicio', 'Education/AdminJourneyController@buscarExercicioModulo');
$router->post('/admin/jornadas/modulos/atualizar-exercicio', 'Education/AdminJourneyController@atualizarExercicioModulo');
$router->get('/admin/jornadas/modulos/{modulo_id}/videos', 'Education/AdminJourneyController@gerenciarVideosModulo');
$router->post('/admin/jornadas/modulos/adicionar-video', 'Education/AdminJourneyController@adicionarVideoModulo');
$router->post('/admin/jornadas/modulos/remover-video', 'Education/AdminJourneyController@removerVideoModulo');
$router->post('/admin/jornadas/modulos/adicionar-documento', 'Education/AdminJourneyController@adicionarDocumentoModulo');
$router->post('/admin/jornadas/modulos/remover-documento', 'Education/AdminJourneyController@removerDocumentoModulo');
$router->get('/admin/jornadas/modulos/{modulo_id}/redacao', 'Education/AdminJourneyController@gerenciarRedacaoModulo');
$router->post('/admin/jornadas/modulos/salvar-tema-redacao', 'Education/AdminJourneyController@salvarTemaRedacaoModulo');

// Mural de Recados (Admin)
$router->get('/admin/mural-recados', 'Admin/AdminMuralController@index');
$router->get('/admin/mural-recados/criar', 'Admin/AdminMuralController@criar');
$router->post('/admin/mural-recados/salvar', 'Admin/AdminMuralController@salvar');
$router->get('/admin/mural-recados/editar', 'Admin/AdminMuralController@editar');
$router->post('/admin/mural-recados/atualizar', 'Admin/AdminMuralController@atualizar');
$router->post('/admin/mural-recados/excluir', 'Admin/AdminMuralController@excluir');

// Comunicação Escolar (independente do mural professor -> aluno)
$router->get('/admin/comunicacao-escolar', 'Admin/SchoolCommunicationController@index');
$router->get('/admin/comunicacao-escolar/nova', 'Admin/SchoolCommunicationController@create');
$router->post('/admin/comunicacao-escolar', 'Admin/SchoolCommunicationController@store');
$router->get('/admin/comunicacao-escolar/{id}', 'Admin/SchoolCommunicationController@show');
$router->post('/admin/comunicacao-escolar/{id}/responder', 'Admin/SchoolCommunicationController@reply');
$router->get('/admin/calendario-escolar', 'Admin/SchoolCommunicationController@calendar');
$router->get('/admin/calendario-escolar/novo', 'Admin/SchoolCommunicationController@calendarCreate');
$router->get('/admin/calendario-escolar/{id}/editar', 'Admin/SchoolCommunicationController@calendarEdit');
$router->post('/admin/calendario-escolar', 'Admin/SchoolCommunicationController@calendarStore');
$router->post('/admin/calendario-escolar/{id}', 'Admin/SchoolCommunicationController@calendarUpdate');
$router->post('/admin/calendario-escolar/{id}/cancelar', 'Admin/SchoolCommunicationController@calendarCancel');
$router->get('/admin/aulas-online', 'Admin/OnlineClassController@index');
$router->get('/admin/aulas-online/criar', 'Admin/OnlineClassController@criar');
$router->get('/admin/aulas-online/editar', 'Admin/OnlineClassController@editar');
$router->get('/admin/aulas-online/chat', 'Admin/OnlineClassController@chat');
$router->get('/admin/aulas-online/chat/mensagens', 'Admin/OnlineClassController@chatMensagens');
$router->post('/admin/aulas-online/chat/enviar', 'Admin/OnlineClassController@chatEnviar');
$router->post('/admin/aulas-online/salvar', 'Admin/OnlineClassController@salvar');
$router->post('/admin/aulas-online/atualizar', 'Admin/OnlineClassController@atualizar');
$router->post('/admin/aulas-online/retry-integracao', 'Admin/OnlineClassController@retryIntegracao');
$router->post('/admin/aulas-online/excluir', 'Admin/OnlineClassController@excluir');
$router->post('/admin/aulas-online/arquivo/upload', 'Admin/OnlineClassController@uploadArquivo');
$router->post('/admin/aulas-online/arquivo/excluir', 'Admin/OnlineClassController@excluirArquivo');
$router->get('/admin/aulas-online/arquivo/download', 'Admin/OnlineClassController@downloadArquivo');

// EducaInclui — Provas adaptativas por laudo (Coordenação/AEE)
$router->get('/admin/inclusao', 'EducaInclui/EducaIncluiController@index');
$router->post('/admin/inclusao/salvar', 'EducaInclui/EducaIncluiController@salvar');
$router->post('/admin/inclusao/ativar', 'EducaInclui/EducaIncluiController@ativar');
$router->post('/admin/inclusao/aprovar-reforcada', 'EducaInclui/EducaIncluiController@aprovarReforcada');
$router->post('/admin/inclusao/analisar-laudo', 'EducaInclui/EducaIncluiController@analisarLaudo');
$router->get('/admin/inclusao/versoes', 'EducaInclui/EducaIncluiController@versoes');
$router->post('/admin/inclusao/versoes/gerar', 'EducaInclui/EducaIncluiController@gerarPendentes');
$router->get('/admin/inclusao/versoes/{id}/diff', 'EducaInclui/EducaIncluiController@verVersao');
$router->get('/admin/inclusao/versoes/{id}/pdf', 'EducaInclui/EducaIncluiController@pdfVersao');
$router->post('/admin/inclusao/versoes/aprovar', 'EducaInclui/EducaIncluiController@aprovarVersao');
$router->post('/admin/inclusao/versoes/questao/refazer', 'EducaInclui/EducaIncluiController@refazerQuestaoVersao');
$router->post('/admin/inclusao/versoes/questao/editar', 'EducaInclui/EducaIncluiController@editarQuestaoVersao');
$router->post('/admin/inclusao/status', 'EducaInclui/EducaIncluiController@status');
$router->post('/admin/inclusao/laudo/upload', 'EducaInclui/EducaIncluiController@uploadLaudo');
$router->get('/admin/inclusao/laudo/{id}', 'EducaInclui/EducaIncluiController@laudo');
$router->get('/admin/inclusao/aluno/{id}', 'EducaInclui/EducaIncluiController@manage');
$router->get('/admin/inclusao/aluno/{id}/resumo', 'EducaInclui/EducaIncluiController@resumoJson');

// Gestão de Jornadas (antiga - redireciona para rotas novas)
$router->get('/admin/journeys', function() {
    header('Location: ' . URL . '/admin/jornadas');
    exit;
});
$router->get('/admin/journeys/create', function() {
    header('Location: ' . URL . '/admin/jornadas/criar');
    exit;
});
// Compatibilidade: redireciona URLs antigas em inglês para as novas em português
$router->get('/admin/journeys/{id}', function($id) {
    header('Location: ' . URL . '/admin/jornadas/' . $id);
    exit;
});
$router->get('/admin/journeys/{id}/edit', function($id) {
    header('Location: ' . URL . '/admin/jornadas/' . $id);
    exit;
});

// Gestão de Exercícios
$router->get('/admin/exercises', 'Admin/ExerciseAdminController@exercicios');
$router->get('/admin/exercises/create', 'Admin/ExerciseAdminController@criarExercicio');
$router->post('/admin/exercises', 'Admin/ExerciseAdminController@salvarExercicio');
$router->get('/admin/exercises/{id}/edit', 'Admin/ExerciseAdminController@editarExercicio');
$router->put('/admin/exercises/{id}', 'Admin/ExerciseAdminController@atualizarExercicio');
$router->delete('/admin/exercises/{id}', 'Admin/ExerciseAdminController@excluirExercicio');
$router->post('/admin/exercises/import', 'Admin/ExerciseAdminController@importarExercicios');
$router->get('/admin/exercises/export', 'Admin/ExerciseAdminController@exportarExercicios');
$router->get('/admin/exercises/{id}/export', 'Admin/ExerciseAdminController@exportarExercicios');

// Gerenciamento de Questões
$router->get('/admin/exercises/{id}/questions', 'Admin/ExerciseAdminController@gerenciarQuestoes');
$router->get('/admin/exercises/{id}/questions/create', 'Admin/ExerciseAdminController@adicionarQuestao');
$router->post('/admin/exercises/{id}/questions', 'Admin/ExerciseAdminController@salvarQuestao');
$router->get('/admin/exercises/{listaId}/questions/{questaoId}/edit', 'Admin/ExerciseAdminController@editarQuestao');
$router->put('/admin/exercises/{listaId}/questions/{questaoId}', 'Admin/ExerciseAdminController@atualizarQuestao');
$router->delete('/admin/exercises/{listaId}/questions/{questaoId}', 'Admin/ExerciseAdminController@excluirQuestao');
$router->post('/admin/exercises/{id}/questions/reorder', 'Admin/ExerciseAdminController@reordenarQuestoes');

// Configurações
$router->get('/admin/settings', 'Admin/SchoolSettingsAdminController@configuracoes');
$router->put('/admin/settings', 'User/AdminController@salvarConfiguracoes');
$router->post('/admin/settings/sliders-dashboard', 'Admin/SchoolSettingsAdminController@salvarSlidersDashboard');
$router->get('/admin/boletim', 'Admin/BoletimConfigController@listagem');
$router->get('/admin/boletim/geracao-status', 'Admin/BoletimConfigController@geracaoStatusJson');
$router->get('/admin/boletim-guia', 'Admin/BoletimGuiaController@index');
$router->get('/admin/boletim-configuracao', 'Admin/BoletimConfigController@index');
$router->get('/admin/boletim-configuracao/assistente', 'Admin/BoletimConfigController@assistente');
$router->get('/admin/boletim-configuracao/jornadas', 'Admin/BoletimConfigController@jornadasJson');
$router->get('/admin/boletim-configuracao/evento-componentes', 'Admin/BoletimConfigController@eventoComponentesJson');
$router->get('/admin/boletim-configuracao/keepalive', 'Admin/BoletimConfigController@keepalive');
$router->post('/admin/boletim-configuracao/salvar', 'Admin/BoletimConfigController@salvarRegra');
$router->post('/admin/boletim-configuracao/excluir-regra', 'Admin/BoletimConfigController@excluirRegra');
$router->post('/admin/boletim-configuracao/duplicar-regra', 'Admin/BoletimConfigController@duplicarRegra');
$router->post('/admin/boletim-configuracao/visibilidade-regra', 'Admin/BoletimConfigController@alternarVisibilidadeRegra');
$router->post('/admin/boletim-configuracao/notas-manuais', 'Admin/BoletimConfigController@salvarNotasManuais');
$router->post('/admin/boletim-configuracao/nota-manual-materia-ajax', 'Admin/BoletimConfigController@salvarNotaManualMateriaAjax');
$router->post('/admin/boletim-configuracao/gerar-boletins', 'Admin/BoletimConfigController@gerarBoletins');
$router->get('/admin/boletim-configuracao/checklist-pre-geracao', 'Admin/BoletimConfigController@checklistPreGeracao');
$router->get('/admin/boletim-configuracao/logs-geracao', 'Admin/BoletimConfigController@logsGeracaoJson');
$router->get('/admin/boletim-configuracao/geracao-detalhe', 'Admin/BoletimConfigController@geracaoDetalheJson');
$router->post('/admin/boletim-configuracao/travar-aluno', 'Admin/BoletimConfigController@travarAluno');
$router->post('/admin/boletim-configuracao/destravar-aluno', 'Admin/BoletimConfigController@destravarAluno');
$router->post('/admin/boletim-configuracao/simular-lote', 'Admin/BoletimConfigController@simularLote');
$router->post('/admin/boletim-configuracao/publicar-boletim-aluno', 'Admin/BoletimConfigController@publicarBoletimAlunoSimulado');
$router->post('/admin/boletim-configuracao/atualizar-boletins-gravados', 'Admin/BoletimConfigController@atualizarBoletinsGravados');
$router->get('/admin/boletim-configuracao/gerados', 'Admin/BoletimConfigController@boletinsGerados');
$router->get('/admin/boletim-configuracao/gerados/preview', 'Admin/BoletimConfigController@boletimGeradoPreview');
$router->post('/admin/boletim-configuracao/gerados/excluir', 'Admin/BoletimConfigController@excluirBoletimGeradoAdmin');
$router->post('/admin/boletim-configuracao/gerados/excluir-lote', 'Admin/BoletimConfigController@excluirBoletimGeradoLote');
$router->get('/admin/boletim-configuracao/assistente/contexto', 'Admin/BoletimAssistenteController@contexto');
$router->post('/admin/boletim-configuracao/assistente/mensagem', 'Admin/BoletimAssistenteController@mensagem');
$router->post('/admin/boletim-configuracao/assistente/mensagem-stream', 'Admin/BoletimAssistenteController@mensagemStream');
$router->post('/admin/boletim-configuracao/assistente/ferramenta', 'Admin/BoletimAssistenteController@ferramenta');
$router->post('/admin/boletim-configuracao/assistente/wizard/inicio', 'Admin/BoletimAssistenteController@wizardInicio');
$router->post('/admin/boletim-configuracao/assistente/wizard/montar', 'Admin/BoletimAssistenteController@wizardMontar');

// MCP / consulta de provas dos alunos (somente leitura)
$router->post('/admin/consulta-provas-aluno/mcp/ferramenta', 'Admin/ProvasAlunoMcpController@ferramenta');

// MCP / consulta de provas na visão do professor (somente leitura)
$router->post('/admin/consulta-provas-professor/mcp/ferramenta', 'Admin/ProvasProfessorMcpController@ferramenta');

$router->post('/admin/consulta-jornadas-aluno/mcp/ferramenta', 'Admin/JornadasAlunoMcpController@ferramenta');

// MCP / turma, bloco, jornadas professor, boletim e faltas (somente leitura)
$router->post('/admin/consulta-assistente/mcp/ferramenta', 'Admin/AssistenteConsultaMcpController@ferramenta');

// Assistente (chat IA + histórico — coordenação)
$router->get('/admin/assistente', 'Admin/ProvasAlunoAssistenteController@index');
$router->get('/admin/assistente/conversas', 'Admin/ProvasAlunoAssistenteController@listarConversas');
$router->get('/admin/assistente/conversa', 'Admin/ProvasAlunoAssistenteController@obterConversa');
$router->post('/admin/assistente/conversas/criar', 'Admin/ProvasAlunoAssistenteController@criarConversa');
$router->post('/admin/assistente/conversas/renomear', 'Admin/ProvasAlunoAssistenteController@renomearConversa');
$router->post('/admin/assistente/conversas/excluir', 'Admin/ProvasAlunoAssistenteController@excluirConversa');
$router->post('/admin/assistente/mensagem', 'Admin/ProvasAlunoAssistenteController@mensagem');
$router->post('/admin/assistente/mensagem-stream', 'Admin/ProvasAlunoAssistenteController@mensagemStream');
$router->post('/admin/assistente/ferramenta', 'Admin/ProvasAlunoAssistenteController@ferramenta');
// Alias legado
$router->get('/admin/assistente-provas', 'Admin/ProvasAlunoAssistenteController@index');
$router->post('/admin/assistente-provas/mensagem', 'Admin/ProvasAlunoAssistenteController@mensagem');
$router->post('/admin/assistente-provas/mensagem-stream', 'Admin/ProvasAlunoAssistenteController@mensagemStream');
$router->post('/admin/assistente-provas/ferramenta', 'Admin/ProvasAlunoAssistenteController@ferramenta');

// Wiki visual dos Markdowns em doc_sistema/
$router->get('/admin/doc-sistema', 'Admin/DocSistemaController@index');
$router->get('/admin/doc-sistema/{pagina}', 'Admin/DocSistemaController@index');

// Faltas (Admin/Coordenação)
$router->get('/admin/faltas', 'Admin/SchoolAbsenceController@index');
$router->get('/admin/faltas/exportar-excel', 'Admin/SchoolAbsenceController@exportarExcel');
$router->get('/admin/faltas/lancar', 'Admin/SchoolAbsenceController@lancar');
$router->get('/admin/faltas/lancar/exportar-excel', 'Admin/SchoolAbsenceController@exportarLancamentoExcel');
$router->post('/admin/faltas/criar', 'Admin/SchoolAbsenceController@criarEvento');
$router->post('/admin/faltas/atualizar', 'Admin/SchoolAbsenceController@atualizarEvento');
$router->post('/admin/faltas/salvar', 'Admin/SchoolAbsenceController@salvarLancamentos');
$router->post('/admin/faltas/excluir', 'Admin/SchoolAbsenceController@excluirEvento');

// Diário de Classe: rotas em app/Modulos/diario/routes.php

// Painel de Conformidade Pedagógica / Central de Pendências / Modo Auditoria
$router->get('/admin/conformidade', 'Admin/ComplianceController@dashboard');
$router->get('/admin/conformidade/pendencias', 'Admin/ComplianceController@pendencias');
$router->get('/admin/conformidade/auditoria', 'Admin/ComplianceController@auditoria');
$router->get('/admin/conformidade/ia', 'Admin/ComplianceController@ia');
$router->post('/admin/conformidade/ia/perguntar', 'Admin/ComplianceController@iaPerguntar');

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
$router->post('/admin/calendario-letivo/salvar-evento', 'Admin/SchoolCalendarController@salvarEvento');
$router->post('/admin/calendario-letivo/excluir-evento', 'Admin/SchoolCalendarController@excluirEvento');

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
$router->post('/admin/dev/financeiro/save', 'Admin/FinanceAdminController@salvarFinanceiroConfig');

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

// Perfis de Permissão (Admin escola)
$router->get('/admin/permissoes-perfis', 'User/AdminPermissionProfileController@index');
$router->get('/admin/permissoes-perfis/{id}/dados', 'User/AdminPermissionProfileController@dados');

// ── Financeiro Escolar ────────────────────────────────────────────────────
$router->get('/admin/finance',                                      'Admin/FinanceController@dashboard');
$router->post('/admin/finance/disparar-regua',                      'Admin/FinanceController@dispararRegua');
$router->get('/admin/finance/contracts',                            'Admin/FinanceController@contractIndex');
$router->get('/admin/finance/contracts/create',                     'Admin/FinanceController@contractCreate');
$router->post('/admin/finance/contracts',                           'Admin/FinanceController@contractStore');
$router->get('/admin/finance/contracts/{id}',                       'Admin/FinanceController@contractShow');
$router->post('/admin/finance/contracts/{id}/activate',             'Admin/FinanceController@contractActivate');
$router->post('/admin/finance/contracts/{id}/cancel',               'Admin/FinanceController@contractCancel');
$router->post('/admin/finance/contracts/{id}/discounts/add',        'Admin/FinanceController@discountAdd');
$router->post('/admin/finance/contracts/{contractId}/discounts/{discountId}/approve', 'Admin/FinanceController@discountApprove');
$router->post('/admin/finance/contracts/{contractId}/discounts/{discountId}/reject',  'Admin/FinanceController@discountReject');
$router->post('/admin/finance/contracts/{contractId}/discounts/{discountId}/remove',  'Admin/FinanceController@discountRemove');
$router->get('/admin/finance/installments/{id}',                    'Admin/FinanceController@installmentShow');
$router->post('/admin/finance/installments/{id}/pay',               'Admin/FinanceController@installmentPay');
$router->get('/admin/finance/installments/{id}/boleto',             'Admin/FinanceController@installmentBoleto');
$router->get('/admin/finance/price-table',                          'Admin/FinanceController@priceTable');
$router->post('/admin/finance/price-table',                         'Admin/FinanceController@priceTableStore');
$router->post('/admin/finance/price-table/{id}/delete',             'Admin/FinanceController@priceTableDelete');
$router->get('/admin/finance/discount-rules',                       'Admin/FinanceController@discountRules');
$router->post('/admin/finance/discount-rules',                      'Admin/FinanceController@discountRuleStore');
$router->post('/admin/finance/discount-rules/{id}/toggle',          'Admin/FinanceController@discountRuleToggle');
$router->get('/admin/finance/report/inadimplencia',                 'Admin/FinanceController@reportInadimplencia');
// Busca de alunos (JSON)
$router->get('/admin/alunos/search',                                 'Admin/FinanceController@alunoSearch');
$router->get('/admin/alunos/{alunoId}/responsaveis',                 'Admin/FinanceController@alunoResponsaveis');
// Planos
$router->get('/admin/finance/plans',                                 'Admin/FinanceController@plansIndex');
$router->get('/admin/finance/plans/create',                          'Admin/FinanceController@planCreate');
$router->post('/admin/finance/plans',                                'Admin/FinanceController@planStore');
$router->get('/admin/finance/plans/{id}',                            'Admin/FinanceController@planShow');
$router->post('/admin/finance/plans/{id}/toggle',                    'Admin/FinanceController@planToggle');
$router->post('/admin/finance/plans/{planId}/items',                 'Admin/FinanceController@planItemStore');
$router->post('/admin/finance/plans/{planId}/items/{itemId}/delete', 'Admin/FinanceController@planItemDelete');
// Extrato / ledger do aluno
$router->get('/admin/finance/aluno/{alunoId}/extrato',               'Admin/FinanceController@alunoExtrato');
$router->post('/admin/finance/ledger/{ledgerId}/estorno',            'Admin/FinanceController@ledgerEstornar');
$router->get('/admin/finance/aluno/{alunoId}/resumo',               'Admin/FinanceController@alunoResumoJson');
$router->get('/admin/finance/aluno/{alunoId}/parcelas-vencidas',    'Admin/FinanceController@alunoParcelasVencidas');
// Lista e gestão de cobranças avulsas
$router->get('/admin/finance/charges',                               'Admin/FinanceController@chargesList');
$router->post('/admin/finance/charges/{chargeId}/pay',               'Admin/FinanceController@chargePay');
// Cobranças avulsas (individual)
$router->get('/admin/finance/aluno/{alunoId}/charge',                'Admin/FinanceController@chargeCreate');
$router->post('/admin/finance/aluno/{alunoId}/charge',               'Admin/FinanceController@chargeStore');
// Cobranças em lote
$router->get('/admin/finance/charges/batch',                         'Admin/FinanceController@chargeBatch');
$router->post('/admin/finance/charges/batch',                        'Admin/FinanceController@chargeBatchStore');
// Fluxo de Caixa
$router->get('/admin/finance/cashflow',                              'Admin/FinanceReportController@cashFlow');
// Contas a Pagar
$router->get('/admin/finance/bills',                                 'Admin/FinanceReportController@billsIndex');
$router->post('/admin/finance/bills',                                'Admin/FinanceReportController@billStore');
$router->post('/admin/finance/bills/{id}/pay',                       'Admin/FinanceReportController@billPay');
$router->post('/admin/finance/bills/{id}/delete',                    'Admin/FinanceReportController@billDelete');
// Relatórios contábeis
$router->get('/admin/finance/reports/dre',                           'Admin/FinanceReportController@dre');
$router->get('/admin/finance/reports/dfc',                           'Admin/FinanceReportController@dfc');
$router->get('/admin/finance/reports/balanco',                       'Admin/FinanceReportController@balanco');
$router->get('/admin/finance/reports/dmpl',                          'Admin/FinanceReportController@dmpl');
$router->get('/admin/finance/reports/dlpa',                          'Admin/FinanceReportController@dlpa');
// Configurações
$router->get('/admin/finance/settings',                              'Admin/FinanceController@settings');
$router->post('/admin/finance/settings',                             'Admin/FinanceController@settingsSave');
// Renegociação
$router->get('/admin/finance/contracts/{contractId}/renegotiation',  'Admin/FinanceController@renegotiationCreate');
$router->post('/admin/finance/contracts/{contractId}/renegotiation', 'Admin/FinanceController@renegotiationStore');
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

// Almoxarifado e Patrimônio
$router->get('/admin/almoxarifado', 'Admin/InventoryAdminController@index');
$router->post('/admin/almoxarifado/itens', 'Admin/InventoryAdminController@storeItem');
$router->post('/admin/almoxarifado/depositos', 'Admin/InventoryAdminController@storeWarehouse');
$router->post('/admin/almoxarifado/fornecedores', 'Admin/InventoryAdminController@storeSupplier');
$router->post('/admin/almoxarifado/movimentacoes', 'Admin/InventoryAdminController@storeMovement');
$router->post('/admin/almoxarifado/requisicoes', 'Admin/InventoryAdminController@storeRequisition');
$router->post('/admin/almoxarifado/requisicoes/{id}/aprovar', 'Admin/InventoryAdminController@approve');
$router->post('/admin/almoxarifado/requisicoes/{id}/rejeitar', 'Admin/InventoryAdminController@reject');
$router->post('/admin/almoxarifado/requisicoes/{id}/atender', 'Admin/InventoryAdminController@fulfill');
$router->get('/admin/patrimonio', 'Admin/PatrimonyAdminController@index');
$router->post('/admin/patrimonio/ambientes', 'Admin/PatrimonyAdminController@storeLocation');
$router->post('/admin/patrimonio/bens', 'Admin/PatrimonyAdminController@storeAsset');
$router->post('/admin/patrimonio/movimentacoes', 'Admin/PatrimonyAdminController@moveAsset');
$router->post('/admin/patrimonio/conferencias', 'Admin/PatrimonyAdminController@checkAsset');

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
$router->get('/admin/avaliacoes',      'Education/AnoLetivoController@avaliacoes');
$router->get('/admin/gestao-escolar',  'Education/AnoLetivoController@gestaoEscolar');
$router->get('/admin/comunicacao',     'Education/AnoLetivoController@comunicacao');
$router->get('/admin/conteudo',            'Education/AnoLetivoController@conteudo');
$router->get('/admin/financeiro-escolar',  'Education/AnoLetivoController@financeiroEscolar');
$router->get('/admin/monitoramento-escolar', 'Education/AnoLetivoController@monitoramento');
$router->get('/admin/relatorios',          'Education/AnoLetivoController@relatorios');
$router->get('/admin/sistema',             'Education/AnoLetivoController@sistema');
$router->get('/admin/gestao-usuarios',     'Education/AnoLetivoController@gestaoUsuarios');
$router->get('/admin/z-configuracao',      'Education/AnoLetivoController@zConfiguracao');

// Ano Letivo (CRUD - estrutura normalizada, offcanvas em index)
$router->get('/admin/ano-letivo', 'Education/AnoLetivoController@index');
$router->get('/admin/ano-letivo/{id}/dados', 'Education/AnoLetivoController@dados');
$router->post('/admin/ano-letivo', 'Education/AnoLetivoController@store');
$router->post('/admin/ano-letivo/{id}/update', 'Education/AnoLetivoController@update');
$router->post('/admin/ano-letivo/{id}/delete', 'Education/AnoLetivoController@destroy');

// Curso (CRUD - tabela curso, estrutura normalizada, offcanvas em index)
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
$router->get('/admin/reports/boletim-coordenacao/exportar', 'Admin/ReportAdminController@exportarBoletimCoordenacao');

// Financeiro
// Legado: redireciona /admin/financeiro (SaaS billing) → novo módulo escolar
$router->get('/admin/financeiro', function() { header('Location: ' . URL . '/admin/finance'); exit; });
$router->get('/admin/financeiro/relatorio-pagantes', function() { header('Location: ' . URL . '/admin/finance/report/inadimplencia'); exit; });

// Minicursos (Admin)
$router->get('/admin/minicursos', 'Minicursos/AdminMinicursoController@index');
$router->get('/admin/minicursos/criar', 'Minicursos/AdminMinicursoController@create');
$router->post('/admin/minicursos/salvar', 'Minicursos/AdminMinicursoController@store');

$router->get('/admin/minicursos/{id}', 'Minicursos/AdminMinicursoController@show');
$router->get('/admin/minicursos/editar/{id}', 'Minicursos/AdminMinicursoController@edit');
$router->post('/admin/minicursos/atualizar/{id}', 'Minicursos/AdminMinicursoController@update');
$router->post('/admin/minicursos/excluir/{id}', 'Minicursos/AdminMinicursoController@delete');
$router->post('/admin/minicursos/modulo/salvar', 'Minicursos/AdminMinicursoController@moduloStore');
$router->post('/admin/minicursos/modulo/excluir', 'Minicursos/AdminMinicursoController@moduloDelete');
$router->post('/admin/minicursos/aula/salvar', 'Minicursos/AdminMinicursoController@aulaStore');
$router->post('/admin/minicursos/aula/atualizar', 'Minicursos/AdminMinicursoController@aulaUpdate');
$router->post('/admin/minicursos/aula/excluir', 'Minicursos/AdminMinicursoController@aulaDelete');

// Planos de Aula (Admin)
$router->get('/admin/planos-aula', 'Education/LessonPlanController@indexAdmin');
$router->get('/admin/planos-aula/visualizar/{id}', 'Education/LessonPlanController@visualizar');
$router->get('/admin/planos-aula/editar/{id}', 'Education/LessonPlanController@editar');
$router->post('/admin/planos-aula/atualizar/{id}', 'Education/LessonPlanController@atualizar');
$router->post('/admin/planos-aula/aprovar-rejeitar/{id}', 'Education/LessonPlanController@aprovarRejeitar');
$router->post('/admin/planos-aula/aprovar-rascunhos', 'Education/LessonPlanController@aprovarTodosRascunhosAdmin');
$router->get('/admin/planos-aula/pdf/{id}', 'Education/LessonPlanController@exportarPdf');

// Provas Online (Admin)
$router->get('/admin/provas', 'Exams/ExamController@indexAdmin');
$router->get('/admin/provas/visualizar/{id}', 'Exams/ExamController@visualizar');
$router->get('/admin/provas/editar/{id}', 'Exams/ExamController@editar');
$router->post('/admin/provas/liberar/{id}', 'Exams/ExamController@liberarProva');
$router->post('/admin/provas/{id}/reprovar', 'Exams/ExamController@reprovar');
$router->post('/admin/provas/{id}/remover', 'Exams/ExamController@remover');
$router->post('/admin/provas/{id}/retornar', 'Exams/ExamController@retornar');
$router->get('/admin/provas/{id}/pdf', 'Exams/ExamController@gerarPdf');
$router->get('/admin/provas/{id}/imprimir', 'Exams/ExamController@verImpressao');
$router->post('/admin/provas/corrigir-questao/{id}/{alunoId}/{questaoId}', 'Exams/ExamController@corrigirQuestao');
$router->post('/admin/provas/questoes/{questaoId}/invalidar', 'Exams/ExamController@invalidarQuestao');
$router->post('/admin/provas/{id}/observacao-coordenacao', 'Exams/ExamController@salvarObservacaoCoordenacao');
$router->get('/admin/provas/resultado-aluno/{id}/{alunoId}/historico-respostas', 'Exams/ExamController@historicoRespostasAluno');
$router->get('/admin/provas/resultado-aluno/{id}/{alunoId}', 'Exams/ExamController@visualizarResultadoAluno');
$router->post('/admin/provas/liberar-tentativa/{provaId}/{alunoId}', 'Exams/ExamController@liberarTentativa');
$router->post('/admin/provas/validar-tentativa/{provaId}/{alunoId}', 'Exams/ExamController@validarTentativaCancelada');

// Blocos de Provas (Admin/Coordenador/Diretor)
// Redireciona /admin/provas/blocos para /admin/provas
$router->get('/admin/provas/blocos', function() {
    header('Location: ' . URL . '/admin/provas');
    exit;
});
$router->get('/admin/provas/blocos/criar', 'Exams/ExamBlockController@criar');
$router->post('/admin/provas/blocos', 'Exams/ExamBlockController@salvar');
// Lote (antes das rotas com {id})
$router->post('/admin/provas/blocos/lote/marcar-concluido', 'Exams/ExamBlockController@marcarComoConcluidoLote');
$router->post('/admin/provas/blocos/lote/excluir', 'Exams/ExamBlockController@excluirLote');
$router->get('/admin/provas/blocos/{id}', 'Exams/ExamBlockController@visualizar');
$router->get('/admin/provas/blocos/{id}/editar', 'Exams/ExamBlockController@editar');
$router->post('/admin/provas/blocos/{id}', 'Exams/ExamBlockController@atualizar');
$router->post('/admin/provas/blocos/{id}/duplicar', 'Exams/ExamBlockController@duplicar');
$router->delete('/admin/provas/blocos/{id}', 'Exams/ExamBlockController@excluir');
$router->post('/admin/provas/blocos/{id}/excluir', 'Exams/ExamBlockController@excluir');
$router->post('/admin/provas/blocos/{id}/toggle-liberado', 'Exams/ExamBlockController@toggleLiberado');
$router->post('/admin/provas/blocos/{id}/marcar-concluido', 'Exams/ExamBlockController@marcarComoConcluido');
$router->post('/admin/provas/blocos/{id}/visivel-portal-aluno', 'Exams/ExamBlockController@definirVisivelPortalAluno');
$router->get('/admin/provas/blocos/{id}/gerenciar', 'Teacher/TeacherExamController@gerenciar');
$router->get('/admin/provas/blocos/{id}/notas-lancadas', 'Teacher/TeacherExamController@notasLancadasAdmin');
$router->post('/admin/provas/blocos/{id}/importar-notas-internas', 'Teacher/TeacherExamController@importarNotasInternas');
$router->get('/admin/provas/blocos/{id}/lancar-notas-coordenacao', 'Teacher/TeacherExamController@lancarNotasCoordenacao');
$router->post('/admin/provas/blocos/{id}/lancar-notas-coordenacao', 'Teacher/TeacherExamController@lancarNotasCoordenacaoSalvar');
$router->get('/admin/provas/blocos/{id}/provas-disponiveis', 'Teacher/TeacherExamController@provasDisponiveisParaVincular');
$router->post('/admin/provas/blocos/{id}/vincular', 'Teacher/TeacherExamController@vincularProva');
$router->post('/admin/provas/blocos/{id}/trocar-prova', 'Teacher/TeacherExamController@trocarProva');
$router->get('/admin/provas/blocos/{id}/visualizar-completo', 'Teacher/TeacherExamController@visualizarCompleto');
$router->get('/admin/provas/blocos/{id}/imprimir', 'Exams/ExamController@verImpressaoBlocoCompleto');
$router->get('/admin/provas/blocos/{id}/pdf-completo', 'Exams/ExamController@gerarPdfBlocoCompleto');
$router->get('/admin/provas/blocos/{id}/prova-aluno-pdf', 'Exams/ExamController@gerarPdfProvaAluno');
$router->get('/admin/provas/blocos/{id}/resultados', 'Teacher/TeacherExamController@resultadosBlocoAdmin');
$router->get('/admin/provas/blocos/{id}/resultados-novos', 'Teacher/TeacherExamController@resultadosBlocoAdminNovo');
$router->get('/admin/provas/blocos/{id}/canceladas', 'Teacher/TeacherExamController@canceladasBlocoAdmin');
$router->post('/admin/provas/blocos/{id}/liberar-tentativas', 'Teacher/TeacherExamController@liberarTentativasBlocoAdmin');
$router->get('/admin/provas/blocos/{id}/relatorio-acertos', 'Teacher/TeacherExamController@relatorioAcertosBloco');
$router->get('/admin/provas/blocos/{id}/aluno/{alunoId}/resultado', 'Teacher/TeacherExamController@resultadoAlunoBloco');
$router->post('/admin/provas/blocos/{id}/aluno/{alunoId}/reabrir', 'Teacher/TeacherExamController@reabrirBlocoAluno');
$router->post('/admin/provas/blocos/{id}/liberar-gabarito', 'Teacher/TeacherExamController@liberarGabaritoBloco');
$router->post('/admin/provas/blocos/{id}/aprovar-final', 'Teacher/TeacherExamController@aprovarFinal');

// Blocos Modelo (Templates)
$router->get('/admin/blocos-modelo', 'Exams/ExamBlockModelController@index');
$router->get('/admin/blocos-modelo/criar', 'Exams/ExamBlockModelController@criar');
$router->post('/admin/blocos-modelo', 'Exams/ExamBlockModelController@salvar');
$router->get('/admin/blocos-modelo/{id}/editar', 'Exams/ExamBlockModelController@editar');
$router->post('/admin/blocos-modelo/{id}', 'Exams/ExamBlockModelController@atualizar');
$router->delete('/admin/blocos-modelo/{id}', 'Exams/ExamBlockModelController@excluir');
$router->get('/admin/blocos-modelo/{id}/dados', 'Exams/ExamBlockModelController@dados');

// Tipos de Avaliação (Provas Online)
$router->get('/admin/provas/tipos-avaliacao', 'Exams/ExamEvaluationTypeController@index');
$router->get('/admin/provas/tipos-avaliacao/criar', 'Exams/ExamEvaluationTypeController@criar');
$router->post('/admin/provas/tipos-avaliacao', 'Exams/ExamEvaluationTypeController@salvar');
$router->get('/admin/provas/tipos-avaliacao/{id}/editar', 'Exams/ExamEvaluationTypeController@editar');
$router->post('/admin/provas/tipos-avaliacao/{id}', 'Exams/ExamEvaluationTypeController@atualizar');
$router->delete('/admin/provas/tipos-avaliacao/{id}', 'Exams/ExamEvaluationTypeController@excluir');

// Provas de Professores
$router->get('/professor/provas-professor', 'Teacher/TeacherExamController@index');
$router->post('/professor/provas-professor/{id}/enviar', 'Teacher/TeacherExamController@enviar');
$router->post('/admin/provas-professor/{id}/aprovar', 'Teacher/TeacherExamController@aprovar');
$router->post('/admin/provas-professor/{id}/reprovar', 'Teacher/TeacherExamController@reprovar');

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
$router->get('/admin/ava', 'Admin/AvaCourseAdminController@index');
// Categorias (página própria — um cadastro por tela)
$router->get('/admin/ava/categorias', 'Admin/AvaCourseAdminController@categorias');
$router->post('/admin/ava/categorias', 'Admin/AvaCourseAdminController@storeCategoria');
$router->post('/admin/ava/categorias/{id}/excluir', 'Admin/AvaCourseAdminController@deleteCategoria');
// Cursos (rotas estáticas/compostas antes das com {id})
$router->get('/admin/ava/cursos/novo', 'Admin/AvaCourseAdminController@create');
$router->post('/admin/ava/cursos', 'Admin/AvaCourseAdminController@store');
$router->get('/admin/ava/cursos/{id}/editar', 'Admin/AvaCourseAdminController@edit');
$router->post('/admin/ava/cursos/{id}/excluir', 'Admin/AvaCourseAdminController@delete');
$router->get('/admin/ava/cursos/{id}/periodos', 'Admin/AvaCourseAdminController@periodos');
$router->post('/admin/ava/cursos/{id}/semestres', 'Admin/AvaCourseAdminController@storeSemestre');
$router->post('/admin/ava/cursos/{id}', 'Admin/AvaCourseAdminController@update');
$router->get('/admin/ava/cursos/{id}', 'Admin/AvaCourseAdminController@show');
$router->post('/admin/ava/semestres/{id}/excluir', 'Admin/AvaCourseAdminController@deleteSemestre');
// Disciplinas
$router->post('/admin/ava/disciplinas', 'Admin/AvaDisciplineAdminController@store');
$router->post('/admin/ava/disciplinas/{id}/sincronizar-turma', 'Admin/AvaDisciplineAdminController@syncTurma');
$router->post('/admin/ava/disciplinas/{id}/cancelar-matricula', 'Admin/AvaDisciplineAdminController@unenroll');
$router->post('/admin/ava/disciplinas/{id}/excluir', 'Admin/AvaDisciplineAdminController@delete');
$router->get('/admin/ava/disciplinas/{id}/editar', 'Admin/AvaDisciplineAdminController@edit');
// Avaliações da disciplina (vínculo com prova — um cadastro por tela)
$router->get('/admin/ava/disciplinas/{id}/avaliacoes', 'Admin/AvaDisciplineAdminController@avaliacoes');
$router->post('/admin/ava/disciplinas/{id}/avaliacoes', 'Admin/AvaDisciplineAdminController@storeAvaliacao');
$router->post('/admin/ava/avaliacoes/{id}/excluir', 'Admin/AvaDisciplineAdminController@deleteAvaliacao');
$router->post('/admin/ava/disciplinas/{id}', 'Admin/AvaDisciplineAdminController@update');
$router->get('/admin/ava/disciplinas/{id}', 'Admin/AvaDisciplineAdminController@show');
// Módulos
$router->post('/admin/ava/modulos', 'Admin/AvaContentAdminController@storeModulo');
$router->get('/admin/ava/modulos/{id}/aulas/nova', 'Admin/AvaContentAdminController@createAula');
$router->post('/admin/ava/modulos/{id}/aulas', 'Admin/AvaContentAdminController@storeAula');
$router->post('/admin/ava/modulos/{id}/excluir', 'Admin/AvaContentAdminController@deleteModulo');
$router->post('/admin/ava/modulos/{id}', 'Admin/AvaContentAdminController@updateModulo');
// Aulas
$router->get('/admin/ava/aulas/{id}/editar', 'Admin/AvaContentAdminController@editAula');
$router->post('/admin/ava/aulas/{id}/anexos', 'Admin/AvaContentAdminController@uploadAnexo');
$router->post('/admin/ava/aulas/{id}/excluir', 'Admin/AvaContentAdminController@deleteAula');
$router->post('/admin/ava/aulas/{id}', 'Admin/AvaContentAdminController@updateAula');
// Anexos
$router->post('/admin/ava/anexos/{id}/excluir', 'Admin/AvaContentAdminController@deleteAnexo');

// Módulo de Matrículas e Rematrículas
$router->get('/admin/enrollment',                                   'Admin/EnrollmentAdminController@index');
$router->get('/admin/enrollment/create',                            'Admin/EnrollmentAdminController@create');
$router->post('/admin/enrollment',                                  'Admin/EnrollmentAdminController@store');
$router->get('/admin/enrollment/score',                             'Admin/EnrollmentAdminController@scorePanel');
$router->post('/admin/enrollment/score/recalcular',                 'Admin/EnrollmentAdminController@recalcularScores');
$router->get('/admin/enrollment/{id}',                              'Admin/EnrollmentAdminController@show');
$router->get('/admin/enrollment/{id}/edit',                         'Admin/EnrollmentAdminController@edit');
$router->post('/admin/enrollment/{id}/edit',                        'Admin/EnrollmentAdminController@update');
$router->post('/admin/enrollment/{id}/contrato',                    'Admin/EnrollmentAdminController@gerarContrato');
$router->get('/admin/enrollment/{id}/contrato/download',            'Admin/EnrollmentAdminController@downloadContrato');
$router->post('/admin/enrollment/{id}/status',                      'Admin/EnrollmentAdminController@transicionar');
$router->post('/admin/enrollment/{id}/cancelar',                    'Admin/EnrollmentAdminController@cancelar');
