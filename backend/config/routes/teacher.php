<?php
// Rotas para Professores (perfil: professor)
$router->get('/professor/jornadas/ai-job/{id}/status', 'AIJobController@status');
$router->get('/professor/ai-job/{id}/status', 'AIJobController@status');
$router->get('/professor/dashboard', 'User/TeacherController@dashboard');
$router->get('/professor/carteira', 'User/TeacherController@carteira');
$router->get('/professor/carteira/comprar', 'User/TeacherController@carteiraComprar');
$router->post('/professor/carteira/comprar', 'User/TeacherController@carteiraComprar');
$router->get('/professor/carteira/comprar/aguardando/{id}', 'User/TeacherController@carteiraComprarAguardando');
$router->post('/professor/carteira/comprar/pagar/{id}', 'User/TeacherController@carteiraComprarPagar');
$router->post('/professor/carteira/comprar/verificar/{id}', 'User/TeacherController@carteiraComprarVerificar');
$router->post('/professor/carteira/comprar/status/{id}', 'User/TeacherController@carteiraComprarStatus');
$router->get('/professor/carteira/comprar/simular/{id}', 'User/TeacherController@carteiraComprarSimular');
$router->get('/professor/carteira/planos', 'User/TeacherController@carteiraPlanos');
$router->post('/professor/carteira/planos/assinar', 'User/TeacherController@carteiraPlanosAssinar');
$router->get('/professor/tutoriais', 'User/TeacherController@tutoriais');
$router->post('/professor/upload-foto-perfil', 'User/TeacherController@uploadFotoPerfil');
$router->get('/professor/student', 'User/TeacherController@student');
$router->get('/professor/student/{id}', 'User/TeacherController@viewStudent');
$router->get('/professor/student/{id}/provas', 'User/TeacherController@studentProvas');
$router->get('/professor/student/{id}/relatorio', 'User/TeacherController@studentRelatorio');
$router->post('/professor/student/{id}/password', 'User/TeacherController@updateStudentPassword');

// Gerador de Slides
$router->get('/professor/gerar-slides', 'User/TeacherController@gerarSlides');
$router->get('/professor/meus-slides', 'User/TeacherController@listarSlides');
$router->post('/professor/gerar-slides/api', 'Integrations/SlidesController@gerar');
// Redirecionamento da rota antiga para a nova
$router->get('/professor/student-journey', function() {
    header('Location: ' . URL . '/professor/jornadas');
    exit;
});

// Redirecionamento de /teacher/jornadas para /professor/jornadas
$router->get('/teacher/jornadas', function() {
    header('Location: ' . URL . '/professor/jornadas');
    exit;
});

// Sistema de Jornadas do Professor
$router->get('/professor/jornadas', 'Teacher/TeacherJourneyController@index');
$router->get('/professor/jornadas/criar', 'Teacher/TeacherJourneyController@criar');
$router->get('/professor/jornadas/relatorio', 'User/TeacherController@jornadasRelatorio');
$router->get('/professor/jornadas/buscar-alunos', 'Teacher/TeacherJourneyController@buscarAlunos');
$router->get('/professor/jornadas/buscar-objetivo-plano-aula', 'Teacher/TeacherJourneyController@buscarObjetivoPlanoAula');
$router->post('/professor/jornadas', 'Teacher/TeacherJourneyController@salvar');

// IMPORTANTE: Rotas mais específicas devem vir ANTES das mais genéricas
$router->get('/professor/jornadas/{jornada_id}/exercicios-alunos', 'Teacher/TeacherJourneyController@exerciciosAlunos');
$router->get('/professor/jornadas/{jornada_id}/aluno/{aluno_id}/exercicios', 'Teacher/TeacherJourneyController@verExerciciosAluno');
$router->get('/professor/jornadas/{jornada_id}/exercicios/resultado/{exercicio_id}', 'Teacher/TeacherJourneyController@exercicioResultado');
$router->get('/professor/jornadas/{jornada_id}/mensagens', 'Teacher/TeacherJourneyController@mensagens');
$router->get('/professor/jornadas/{jornada_id}/redacao/{redacao_id}/ver', 'Teacher/TeacherJourneyController@verRedacaoJornada');
$router->get('/professor/jornadas/{jornada_id}/redacao/{redacao_id}/corrigir', 'Teacher/TeacherJourneyController@corrigirRedacaoJornadaForm');
$router->get('/professor/jornadas/{jornada_id}/redacoes', 'Teacher/TeacherJourneyController@listarRedacoesJornada');

// Ver resumo (página única: ver resumo + nota + observações)
$router->get('/professor/jornadas/{id}/resumos/{resumo_id}', 'Teacher/TeacherJourneyController@verResumo');
// Análise de Resumos: redireciona para Resultados > aba Resumos
$router->get('/professor/jornadas/{id}/analise-resumos', 'Teacher/TeacherJourneyController@analiseResumos');

// Rotas /professor/jornadas/modulos/... DEVEM vir ANTES de /professor/jornadas/{id}
// senão a URL /professor/jornadas/modulos/152/dica-professor é capturada por {id} = "modulos"
$router->get('/professor/jornadas/modulos/{modulo_id}/redacao', 'Teacher/TeacherJourneyController@gerenciarRedacaoModulo');
$router->get('/professor/jornadas/modulos/{modulo_id}/exercicios', 'Teacher/TeacherJourneyController@gerenciarExerciciosModulo');
$router->get('/professor/jornadas/modulos/{modulo_id}/exercicios/criar', 'Teacher/TeacherJourneyController@gerenciarExerciciosModuloEditor');
$router->get('/professor/jornadas/modulos/{modulo_id}/videos', 'Teacher/TeacherJourneyController@gerenciarVideosModulo');
$router->get('/professor/jornadas/modulos/{modulo_id}/dica-professor', 'Teacher/TeacherJourneyController@gerenciarDicaProfessorModulo');
$router->get('/professor/jornadas/modulos/{modulo_id}/resumo-aluno', 'Teacher/TeacherJourneyController@gerenciarResumoAlunoModulo');

$router->get('/professor/jornadas/{id}', 'Teacher/TeacherJourneyController@show');
$router->get('/professor/jornadas/{id}/editar', 'Teacher/TeacherJourneyController@editar');
$router->post('/professor/jornadas/{id}/atualizar', 'Teacher/TeacherJourneyController@atualizar');
$router->get('/professor/jornadas/{id}/alunos', 'Teacher/TeacherJourneyController@verAlunos');
$router->post('/professor/jornadas/adicionar-aula', 'Teacher/TeacherJourneyController@adicionarAula');
$router->post('/professor/jornadas/toggle-status', 'Teacher/TeacherJourneyController@toggleStatus');
$router->post('/professor/jornadas/inativar', 'Teacher/TeacherJourneyController@inativarJornada');
$router->post('/professor/jornadas/duplicar', 'Teacher/TeacherJourneyController@duplicarJornada');
$router->get('/professor/jornadas/{id}/modulos', 'Teacher/TeacherJourneyController@gerenciarModulos');
$router->get('/professor/jornadas/{id}/modulos/lista', 'Teacher/TeacherJourneyController@listarModulos');
$router->post('/professor/jornadas/adicionar-modulo', 'Teacher/TeacherJourneyController@adicionarModulo');
$router->post('/professor/jornadas/editar-modulo', 'Teacher/TeacherJourneyController@editarModulo');
$router->post('/professor/jornadas/remover-modulo', 'Teacher/TeacherJourneyController@removerModulo');
$router->post('/professor/jornadas/atualizar-ordem-modulos', 'Teacher/TeacherJourneyController@atualizarOrdemModulos');

// Exercícios das Jornadas (rotas específicas já movidas para cima)
$router->get('/professor/jornadas/{id}/exercicios/{exercicio_id}/editar', 'Teacher/TeacherJourneyController@exercicioForm');
$router->get('/professor/jornadas/{id}/exercicios/criar', 'Teacher/TeacherJourneyController@exercicioForm');
$router->get('/professor/jornadas/{id}/exercicios/ia', 'Teacher/TeacherJourneyController@exercicioIAForm');
$router->get('/professor/jornadas/{id}/exercicios', 'Teacher/TeacherJourneyController@exercicios');
$router->post('/professor/jornadas/criar-exercicio', 'Teacher/TeacherJourneyController@criarExercicio');
$router->post('/professor/jornadas/gerar-exercicio-ia', 'Teacher/TeacherJourneyController@gerarExercicioIA');
$router->post('/professor/jornadas/aprovar-exercicio-ia', 'Teacher/TeacherJourneyController@aprovarExercicioIA');
$router->post('/professor/jornadas/publicar-exercicio', 'Teacher/TeacherJourneyController@publicarExercicio');

// Blocos de Conteúdo das Jornadas
$router->get('/professor/jornadas/{id}/blocos', 'Education/ContentBlockController@index');
$router->post('/professor/jornadas/{id}/blocos/adicionar', 'Education/ContentBlockController@adicionarBloco');
$router->post('/professor/jornadas/{id}/blocos/atualizar-ordem', 'Education/ContentBlockController@atualizarOrdem');
$router->post('/professor/jornadas/{id}/blocos/{bloco_id}/editar', 'Education/ContentBlockController@editarBloco');
$router->post('/professor/jornadas/{id}/blocos/{bloco_id}/remover', 'Education/ContentBlockController@removerBloco');
$router->get('/professor/jornadas/exercicios/{id}', 'Teacher/TeacherJourneyController@buscarExercicio');

// Buscar resumo específico (já movido para cima: analise-resumos)
$router->get('/professor/jornadas/resumos/{id}', 'Teacher/TeacherJourneyController@buscarResumo');
$router->post('/professor/jornadas/resumos/atribuir-nota', 'Teacher/TeacherJourneyController@atribuirNotaResumo');
$router->post('/professor/jornadas/exercicios/atribuir-nota-dissertativa', 'Teacher/TeacherJourneyController@atribuirNotaDissertativa');
$router->post('/professor/jornadas/gerar-explicacao-complementar', 'Teacher/TeacherJourneyController@gerarExplicacaoComplementar');

// Dúvidas e Comunicação
$router->post('/professor/jornadas/responder-duvida', 'Teacher/TeacherJourneyController@responderDuvida');
$router->post('/professor/jornadas/responder-mensagem', 'Teacher/TeacherJourneyController@responderMensagem');
// Redações da Jornada (Professor) - rotas específicas já movidas para cima
$router->post('/professor/jornadas/sugerir-tema-redacao', 'Teacher/TeacherJourneyController@sugerirTemaRedacao');
$router->post('/professor/jornadas/corrigir-redacao', 'Teacher/TeacherJourneyController@corrigirRedacaoJornada');
$router->post('/professor/jornadas/corrigir-redacao-ia', 'Teacher/TeacherJourneyController@corrigirRedacaoJornadaIA');
$router->get('/professor/jornadas/corrigir-redacao-ia/status/{id}', 'Teacher/TeacherJourneyController@corrigirRedacaoJornadaIAStatus');
$router->post('/professor/jornadas/permitir-refazer-redacao', 'Teacher/TeacherJourneyController@permitirRefazerRedacao');
$router->post('/professor/jornadas/escolher-correcao', 'Teacher/TeacherJourneyController@escolherCorrecao');
$router->post('/professor/jornadas/retornar-reescrever', 'Teacher/TeacherJourneyController@retornarParaReescrever');
$router->post('/professor/jornadas/excluir', 'Teacher/TeacherJourneyController@excluirJornada');
$router->post('/professor/jornadas/modulos/salvar-tema-redacao', 'Teacher/TeacherJourneyController@salvarTemaRedacaoModulo');
$router->post('/professor/jornadas/modulos/gerar-descricao-redacao-ia', 'Teacher/TeacherJourneyController@gerarDescricaoRedacaoIA');
$router->post('/professor/jornadas/modulos/adicionar-exercicio', 'Teacher/TeacherJourneyController@adicionarExercicioModulo');
$router->get('/professor/jornadas/modulos/banco-questoes/facets', 'Teacher/TeacherJourneyController@apiBancoQuestoesFacetsModulo');
$router->get('/professor/jornadas/modulos/banco-questoes/listar', 'Teacher/TeacherJourneyController@apiBancoQuestoesListarModulo');
$router->post('/professor/jornadas/modulos/banco-questoes/importar', 'Teacher/TeacherJourneyController@apiBancoQuestoesImportarModulo');
$router->post('/professor/jornadas/modulos/upload-imagem-exercicio', 'Teacher/TeacherJourneyController@uploadImagemExercicio');
$router->post('/professor/jornadas/modulos/gerar-exercicio-ia', 'Teacher/TeacherJourneyController@gerarExercicioIAModulo');
$router->post('/professor/jornadas/modulos/importar-exercicios-ia/{jobId}', 'Teacher/TeacherJourneyController@importarExerciciosModuloIA');
$router->post('/professor/jornadas/modulos/ler-imagem-exercicio/{modulo_id}', 'Teacher/TeacherJourneyController@lerImagemExercicio');
$router->post('/professor/jornadas/modulos/remover-exercicio', 'Teacher/TeacherJourneyController@removerExercicioModulo');
$router->post('/professor/jornadas/modulos/alternar-status-exercicio', 'Teacher/TeacherJourneyController@alternarStatusExercicio');
$router->get('/professor/jornadas/modulos/buscar-exercicio', 'Teacher/TeacherJourneyController@buscarExercicioModulo');
$router->post('/professor/jornadas/modulos/atualizar-exercicio', 'Teacher/TeacherJourneyController@atualizarExercicioModulo');

// EducaProf (chat) - construção de jornada via JSON (SSO professor)
$router->get('/professor/api/educaprof/jornadas/contexto', 'Teacher/TeacherJourneyController@apiEducaProfJornadasContexto');
$router->post('/professor/api/educaprof/jornadas/dry-run', 'Teacher/TeacherJourneyController@apiEducaProfJornadasDryRun');
$router->post('/professor/api/educaprof/jornadas/criar', 'Teacher/TeacherJourneyController@apiEducaProfJornadasCriar');
$router->post('/professor/jornadas/modulos/{modulo_id}/salvar-descricao-resumo', 'Teacher/TeacherJourneyController@salvarDescricaoResumoModulo');
$router->post('/professor/jornadas/modulos/adicionar-video', 'Teacher/TeacherJourneyController@adicionarVideoModulo');
$router->post('/professor/jornadas/modulos/remover-video', 'Teacher/TeacherJourneyController@removerVideoModulo');
$router->post('/professor/jornadas/modulos/adicionar-documento', 'Teacher/TeacherJourneyController@adicionarDocumentoModulo');
$router->post('/professor/jornadas/modulos/remover-documento', 'Teacher/TeacherJourneyController@removerDocumentoModulo');
$router->post('/professor/jornadas/modulos/adicionar-texto', 'Teacher/TeacherJourneyController@adicionarTextoModulo');
$router->post('/professor/jornadas/modulos/remover-texto', 'Teacher/TeacherJourneyController@removerTextoModulo');

// Mural de Recados (Professor)
$router->get('/professor/mural-recados', 'Teacher/TeacherMuralController@index');
$router->get('/professor/mural-recados/criar', 'Teacher/TeacherMuralController@criar');
$router->post('/professor/mural-recados/salvar', 'Teacher/TeacherMuralController@salvar');
$router->get('/professor/mural-recados/editar', 'Teacher/TeacherMuralController@editar');
$router->post('/professor/mural-recados/atualizar', 'Teacher/TeacherMuralController@atualizar');
$router->post('/professor/mural-recados/excluir', 'Teacher/TeacherMuralController@excluir');

// Teste OpenAI
$router->get('/professor/testar-openai', 'Teacher/TeacherJourneyController@testarOpenAI');

// Chat Global (Professor)
$router->get('/professor/chat', 'Integrations/ChatController@indexProfessor');
$router->get('/professor/chat/{aluno_id}', 'Integrations/ChatController@chatProfessor');
$router->post('/professor/chat/enviar-mensagem', 'Integrations/ChatController@enviarMensagemProfessor');
$router->get('/professor/chat/buscar-mensagens', 'Integrations/ChatController@buscarMensagensProfessor');
// Drive: rotas em app/Modulos/drive/routes.php
// Rotas antigas mantidas para compatibilidade
$router->get('/professor/alunos', 'User/TeacherController@alunos');
$router->get('/professor/jornadas-aluno', 'User/TeacherController@jornadasAluno');

// EducaProf (MVP)
$router->get('/professor/ai-agents', 'User/TeacherAiAgentsController@index');
$router->post('/professor/ai-agents/agent', 'User/TeacherAiAgentsController@createOrUpdateAgent');
$router->post('/professor/ai-agents/ingest', 'User/TeacherAiAgentsController@ingestContent');
$router->post('/professor/ai-agents/chat', 'User/TeacherAiAgentsController@chat');
$router->get('/professor/ai-agents/usage', 'User/TeacherAiAgentsController@usage');

// Redação Configurável (Professor)
$router->get('/professor/redacao-configuravel', 'Essays/TeacherEssayController@index');
$router->get('/professor/redacao-configuravel/relatorio', 'Essays/TeacherEssayController@report');
$router->get('/professor/redacao-configuravel/novo', 'Essays/TeacherEssayController@create');
$router->post('/professor/redacao-configuravel', 'Essays/TeacherEssayController@store');
$router->get('/professor/redacao-configuravel/api/tipos-textuais/{boardId}', 'Essays/TeacherEssayController@apiTextTypes');
$router->get('/professor/redacao-configuravel/api/alunos-by-turmas', 'Essays/TeacherEssayController@apiAlunosByTurmas');
$router->post('/professor/redacao-configuravel/upload-imagem', 'Essays/TeacherEssayController@uploadImage');
$router->post('/professor/redacao-configuravel/gerar-repertorio-ia', 'Essays/TeacherEssayController@gerarRepertorioIA');
$router->post('/professor/redacao-configuravel/upload-tema-pronto', 'Essays/TeacherEssayController@uploadTemaPronto');
$router->post('/professor/redacao-configuravel/propostas/{proposalId}/enviar-aluno', 'Essays/TeacherEssayController@submitForStudent');
$router->post('/professor/redacao-configuravel/propostas/{proposalId}/enviar-lote', 'Essays/TeacherEssayController@submitBatch');
$router->post('/professor/redacao-configuravel/{id}/permissoes/adicionar', 'Essays/TeacherEssayController@grantProfessorPermission');
$router->post('/professor/redacao-configuravel/{id}/permissoes/remover', 'Essays/TeacherEssayController@revokeProfessorPermission');
$router->get('/professor/redacao-configuravel/propostas/{proposalId}/envios/{submissionId}/corrigir', 'Essays/TeacherEssayController@correctForm');
$router->get('/professor/redacao-configuravel/envios/{submissionId}/original', 'Essays/TeacherEssayController@viewOriginal');
$router->post('/professor/redacao-configuravel/envios/{submissionId}/atualizar-texto', 'Essays/TeacherEssayController@updateSubmissionText');
$router->post('/professor/redacao-configuravel/envios/{submissionId}/corrigir-ia', 'Essays/TeacherEssayController@runAICorrection');
$router->post('/professor/redacao-configuravel/envios/{submissionId}/remover-correcao-ia', 'Essays/TeacherEssayController@removeAiCorrection');
$router->post('/professor/redacao-configuravel/envios/{submissionId}/anotacoes', 'Essays/TeacherEssayController@saveTeacherAnnotation');
$router->post('/professor/redacao-configuravel/envios/{submissionId}/anotacoes/remover', 'Essays/TeacherEssayController@removeTeacherAnnotation');
$router->post('/professor/redacao-configuravel/envios/{submissionId}/anotacoes-imagem', 'Essays/TeacherEssayController@saveImageAnnotations');
$router->get('/professor/redacao-configuravel/envios/{submissionId}/imagem-corrigida', 'Essays/TeacherEssayController@viewAnnotated');
$router->post('/professor/redacao-configuravel/envios/{submissionId}/salvar-correcao', 'Essays/TeacherEssayController@saveCorrection');
$router->get('/professor/redacao-configuravel/envios/{submissionId}/audio-feedback', 'Essays/TeacherEssayController@streamTeacherFeedbackAudio');
$router->post('/professor/redacao-configuravel/envios/{submissionId}/audio-feedback', 'Essays/TeacherEssayController@uploadTeacherFeedbackAudio');
$router->post('/professor/redacao-configuravel/envios/{submissionId}/audio-feedback/remover', 'Essays/TeacherEssayController@removeTeacherFeedbackAudio');
$router->get('/professor/redacao-configuravel/{id}/exportar-excel', 'Essays/TeacherEssayController@exportExcel');
$router->get('/professor/redacao-configuravel/{id}', 'Essays/TeacherEssayController@show');
$router->get('/professor/redacao-configuravel/{id}/editar', 'Essays/TeacherEssayController@edit');
$router->put('/professor/redacao-configuravel/{id}', 'Essays/TeacherEssayController@update');
$router->post('/professor/redacao-configuravel/{id}', 'Essays/TeacherEssayController@update');
$router->post('/professor/redacao-configuravel/{id}/toggle-status', 'Essays/TeacherEssayController@toggleStatus');

// Redação Livre (Professor) — upload e correção sem proposta/jornada; módulo habilitável no Master
$router->get('/professor/redacao-livre', 'Essays/TeacherRedacaoLivreController@index');
$router->post('/professor/redacao-livre/upload', 'Essays/TeacherRedacaoLivreController@upload');
$router->get('/professor/redacao-livre/envios/{envioId}/corrigir', 'Essays/TeacherRedacaoLivreController@correctForm');
$router->get('/professor/redacao-livre/envios/{envioId}/original', 'Essays/TeacherRedacaoLivreController@viewOriginal');
$router->post('/professor/redacao-livre/envios/{envioId}/atualizar-texto', 'Essays/TeacherRedacaoLivreController@updateTexto');
$router->post('/professor/redacao-livre/envios/{envioId}/corrigir-ia', 'Essays/TeacherRedacaoLivreController@runAICorrection');
$router->post('/professor/redacao-livre/envios/{envioId}/salvar-correcao', 'Essays/TeacherRedacaoLivreController@saveCorrection');
$router->post('/professor/redacao-livre/envios/{envioId}/meta', 'Essays/TeacherRedacaoLivreController@updateEnvioMeta');
$router->post('/professor/redacao-livre/envios/{envioId}/excluir', 'Essays/TeacherRedacaoLivreController@excluir');

// Questões (Professor) - Banco local + montagem + PDF
$router->get('/professor/questoes', 'Teacher/TeacherQuestionController@index');
$router->post('/professor/questoes/importar', 'Teacher/TeacherQuestionController@importarApi');
$router->post('/professor/questoes/montagens/salvar', 'Teacher/TeacherQuestionController@salvarMontagem');
$router->post('/professor/questoes/pdf/selecionadas', 'Teacher/TeacherQuestionController@baixarPdfSelecionadas');
$router->get('/professor/questoes/montagens/{montagemId}/pdf', 'Teacher/TeacherQuestionController@baixarPdfMontagem');

$router->get('/professor/exercicios', 'User/TeacherController@exercicios');
$router->get('/professor/exercicios/criar', 'User/TeacherController@criarExercicio');
$router->post('/professor/exercicios', 'User/TeacherController@salvarExercicio');
$router->get('/professor/exercicios/{id}/aprovar', 'User/TeacherController@aprovarExercicio');

$router->get('/professor/turmas', 'User/TeacherController@turmas');
$router->get('/professor/turmas/{id}', 'User/TeacherController@turmaDetalhes');
$router->get('/professor/turmas/{id}/alunos', 'User/TeacherController@alunosTurma');

$router->get('/professor/relatorios', 'User/TeacherController@relatorios');
$router->get('/professor/relatorios/turma/{id}', 'User/TeacherController@relatorioTurma');
$router->get('/professor/relatorios/aluno/{id}', 'User/TeacherController@relatorioAluno');

// Diário de Classe (Professor)
$router->get('/professor/diario', 'Teacher/ClassDiaryController@index');
$router->get('/professor/diario/abrir', 'Teacher/ClassDiaryController@abrir');
$router->post('/professor/diario/salvar', 'Teacher/ClassDiaryController@salvar');

// Planos de Aula (Professor)
$router->get('/professor/planos-aula', 'Education/LessonPlanController@index');
$router->get('/professor/planos-aula/criar', 'Education/LessonPlanController@criar');
$router->post('/professor/planos-aula/salvar', 'Education/LessonPlanController@salvar');
$router->get('/professor/planos-aula/visualizar/{id}', 'Education/LessonPlanController@visualizar');
$router->get('/professor/planos-aula/editar/{id}', 'Education/LessonPlanController@editar');
$router->post('/professor/planos-aula/atualizar/{id}', 'Education/LessonPlanController@atualizar');
$router->post('/professor/planos-aula/duplicar/{id}', 'Education/LessonPlanController@duplicar');
$router->post('/professor/planos-aula/excluir/{id}', 'Education/LessonPlanController@excluir');
$router->get('/professor/planos-aula/pdf/{id}', 'Education/LessonPlanController@exportarPdf');

// Arquivos: rotas em app/Modulos/arquivos/routes.php
$router->get('/professor/apostilas', 'Apostilas/TeacherApostilaController@index');
$router->get('/professor/apostilas/abrir/{id}', 'Apostilas/TeacherApostilaController@abrir');
$router->get('/professor/apostilas/visualizar/{id}', 'Apostilas/TeacherApostilaController@visualizar');

// IA da Apostila (módulo novo, independente do módulo Apostilas acima)
$router->get('/professor/apostilas-ia', 'Teacher/TeacherApostilaIaController@index');
$router->get('/professor/apostilas-ia-disponiveis', 'Teacher/TeacherApostilaIaController@apostilasDisponiveisJson');
$router->get('/professor/apostilas-ia/{id}', 'Teacher/TeacherApostilaIaController@abrir');
$router->post('/professor/apostilas-ia/{id}/chat', 'Teacher/TeacherApostilaIaController@chat');
$router->post('/professor/apostilas-ia/{id}/chat-stream', 'Teacher/TeacherApostilaIaController@chatStream');
$router->post('/professor/apostilas-ia/{id}/sessao/nova', 'Teacher/TeacherApostilaIaController@novaSessao');
$router->get('/professor/apostilas-ia/{id}/exercicios', 'Teacher/TeacherApostilaIaController@exercicios');
$router->post('/professor/apostilas-ia/{id}/gerar-prova', 'Teacher/TeacherApostilaIaController@gerarProva');
$router->post('/professor/apostilas-ia/{id}/preparar-slides', 'Teacher/TeacherApostilaIaController@prepararSlides');
$router->get('/professor/apostilas-ia/{id}/pagina/{numeroPagina}/imagem', 'Teacher/TeacherApostilaIaController@imagemPagina');
$router->get('/professor/apostilas-ia/{id}/capa', 'Teacher/TeacherApostilaIaController@capa');
$router->get('/professor/apostilas-ia/{id}/pdf', 'Teacher/TeacherApostilaIaController@pdf');

// Provas Online (Professor)
$router->get('/professor/provas', 'Exams/ExamController@index');
$router->get('/professor/provas-bimestral', 'Exams/ExamController@provasBimestrais');
$router->post('/professor/provas-bimestral/baixar', 'Exams/ExamController@baixarProvasBimestrais');
$router->get('/professor/provas/criar', 'Exams/ExamController@criar');
$router->get('/professor/provas/evento-lancar-notas/{evento_id}', 'Exams/ExamController@eventoLancarNotas');
$router->post('/professor/provas/evento-lancar-notas/{evento_id}', 'Exams/ExamController@eventoLancarNotasSalvar');
$router->get('/professor/provas/criar/evento/{evento_id}', 'Exams/ExamController@criar');
$router->post('/professor/provas/salvar', 'Exams/ExamController@salvar');
$router->get('/professor/provas/visualizar/{id}', 'Exams/ExamController@visualizar');
$router->get('/professor/provas/preview/{id}', 'Exams/ExamController@previewComoAluno');
$router->get('/professor/provas/editar/{id}', 'Exams/ExamController@editar');
$router->post('/professor/provas/atualizar/{id}', 'Exams/ExamController@atualizar');
$router->post('/professor/provas/atualizar-informacoes/{id}', 'Exams/ExamController@atualizarInformacoes');
$router->post('/professor/provas/excluir/{id}', 'Exams/ExamController@excluir');
$router->post('/professor/provas/toggle-liberada/{id}', 'Exams/ExamController@toggleLiberada');
$router->post('/professor/provas/upload-imagem-questao', 'Exams/ExamController@uploadImagemQuestao');
$router->get('/professor/provas/ver-imagem-questao', 'Exams/ExamController@verImagemQuestao');
$router->post('/professor/provas/adicionar-questao/{id}', 'Exams/ExamController@adicionarQuestao');
$router->post('/professor/provas/atualizar-questao/{id}/{questaoId}', 'Exams/ExamController@atualizarQuestao');
$router->post('/professor/provas/finalizar/{id}', 'Exams/ExamController@finalizarProva');
$router->post('/professor/provas/remover-questao/{id}/{questaoId}', 'Exams/ExamController@removerQuestao');
$router->post('/professor/provas/gerar-questoes-ia/{id}', 'Exams/ExamController@gerarQuestoesIA');
$router->post('/professor/provas/gerar-questoes-ia-async/{id}', 'Exams/ExamController@gerarQuestoesIAAsync');
$router->post('/professor/provas/importar-questoes-ia/{jobId}', 'Exams/ExamController@importarQuestoesIA');
$router->post('/professor/provas/ler-imagem-questao/{id}', 'Exams/ExamController@lerImagemQuestao');
$router->get('/professor/provas/banco-questoes/facets/{id}', 'Exams/ExamController@apiBancoQuestoesFacetsProva');
$router->get('/professor/provas/banco-questoes/listar/{id}', 'Exams/ExamController@apiBancoQuestoesListarProva');
$router->post('/professor/provas/banco-questoes/importar/{id}', 'Exams/ExamController@apiBancoQuestoesImportarProva');
$router->post('/professor/provas/corrigir-questao/{id}/{alunoId}/{questaoId}', 'Exams/ExamController@corrigirQuestao');
$router->get('/professor/provas/resultados/{id}', 'Exams/ExamController@resultadosProfessor');
$router->get('/professor/provas/resultado-aluno/{id}/{alunoId}/historico-respostas', 'Exams/ExamController@historicoRespostasAluno');
$router->get('/professor/provas/resultado-aluno/{id}/{alunoId}', 'Exams/ExamController@visualizarResultadoAluno');
$router->post('/professor/provas/liberar-tentativa/{provaId}/{alunoId}', 'Exams/ExamController@liberarTentativa');
$router->post('/professor/provas/validar-tentativa/{provaId}/{alunoId}', 'Exams/ExamController@validarTentativaCancelada');

// Dashboard de Monitoramento (apenas admin) - real-time via SSE
$router->get('/monitoramento', 'MonitoramentoController@index');
$router->get('/api/infra', 'MonitoramentoController@apiInfra');
$router->get('/api/openai-usage', 'MonitoramentoController@apiOpenaiUsage');
$router->get('/api/users-stats', 'MonitoramentoController@apiUsersStats');
$router->get('/api/db-stats', 'MonitoramentoController@apiDbStats');
$router->get('/api/system-health', 'MonitoramentoController@apiSystemHealth');
$router->get('/api/monitoramento/stream', 'MonitoramentoController@apiStream');

// AVA / EAD - Professor (gestão do conteúdo das próprias disciplinas)
$router->get('/professor/ava', 'Teacher/AvaTeacherController@index');
$router->get('/professor/ava/disciplinas/{id}', 'Teacher/AvaTeacherController@show');
$router->post('/professor/ava/modulos', 'Teacher/AvaTeacherController@storeModulo');
$router->get('/professor/ava/modulos/{id}/aulas/nova', 'Teacher/AvaTeacherController@createAula');
$router->post('/professor/ava/modulos/{id}/aulas', 'Teacher/AvaTeacherController@storeAula');
$router->post('/professor/ava/modulos/{id}/excluir', 'Teacher/AvaTeacherController@deleteModulo');
$router->post('/professor/ava/modulos/{id}', 'Teacher/AvaTeacherController@updateModulo');
$router->get('/professor/ava/aulas/{id}/editar', 'Teacher/AvaTeacherController@editAula');
$router->post('/professor/ava/aulas/{id}/anexos', 'Teacher/AvaTeacherController@uploadAnexo');
$router->post('/professor/ava/aulas/{id}/excluir', 'Teacher/AvaTeacherController@deleteAula');
$router->post('/professor/ava/aulas/{id}', 'Teacher/AvaTeacherController@updateAula');
$router->post('/professor/ava/anexos/{id}/excluir', 'Teacher/AvaTeacherController@deleteAnexo');

// AVA / EAD - Professor: atividades, rubricas e correção (Fase 2)
$router->get('/professor/ava/disciplinas/{id}/atividades/nova', 'Teacher/AvaActivityTeacherController@create');
$router->get('/professor/ava/disciplinas/{id}/atividades', 'Teacher/AvaActivityTeacherController@list');
$router->post('/professor/ava/disciplinas/{id}/atividades', 'Teacher/AvaActivityTeacherController@store');
$router->post('/professor/ava/disciplinas/{id}/rubricas', 'Teacher/AvaActivityTeacherController@storeRubrica');
$router->get('/professor/ava/atividades/{id}/editar', 'Teacher/AvaActivityTeacherController@edit');
$router->get('/professor/ava/atividades/{id}/entregas', 'Teacher/AvaActivityTeacherController@submissions');
$router->post('/professor/ava/atividades/{id}/excluir', 'Teacher/AvaActivityTeacherController@delete');
$router->post('/professor/ava/atividades/{id}', 'Teacher/AvaActivityTeacherController@update');
$router->get('/professor/ava/entregas/arquivo/{id}', 'Teacher/AvaActivityTeacherController@downloadFile');
$router->get('/professor/ava/entregas/{id}/corrigir', 'Teacher/AvaActivityTeacherController@gradeForm');
$router->post('/professor/ava/entregas/{id}/corrigir', 'Teacher/AvaActivityTeacherController@grade');
$router->post('/professor/ava/rubricas/{id}/excluir', 'Teacher/AvaActivityTeacherController@deleteRubrica');
// AVA / EAD - Professor: moderação de comentários por aula (Fase 2)
$router->post('/professor/ava/aulas/{id}/comentario', 'Ava/AvaCommentController@store');
$router->post('/professor/ava/comentario/{id}/excluir', 'Ava/AvaCommentController@delete');
$router->post('/professor/ava/comentario/{id}/fixar', 'Ava/AvaCommentController@pin');

// AVA / EAD - Professor: aulas ao vivo (Fase 3)
$router->get('/professor/ava/disciplinas/{id}/ao-vivo/nova', 'Teacher/AvaLiveTeacherController@create');
$router->get('/professor/ava/disciplinas/{id}/ao-vivo', 'Teacher/AvaLiveTeacherController@list');
$router->post('/professor/ava/disciplinas/{id}/ao-vivo', 'Teacher/AvaLiveTeacherController@store');
$router->get('/professor/ava/ao-vivo/{id}/editar', 'Teacher/AvaLiveTeacherController@edit');
$router->get('/professor/ava/ao-vivo/{id}/entrar', 'Teacher/AvaLiveTeacherController@enter');
$router->post('/professor/ava/ao-vivo/{id}/status', 'Teacher/AvaLiveTeacherController@setStatus');
$router->post('/professor/ava/ao-vivo/{id}/gravacao', 'Teacher/AvaLiveTeacherController@setRecording');
$router->post('/professor/ava/ao-vivo/{id}/excluir', 'Teacher/AvaLiveTeacherController@delete');
$router->post('/professor/ava/ao-vivo/{id}', 'Teacher/AvaLiveTeacherController@update');

// Calendário letivo
$router->get('/professor/calendario-letivo', 'User/TeacherController@calendarioLetivo');
