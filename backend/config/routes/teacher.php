<?php
// Jornadas → Modulos/jornadas/routes.php
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
$router->get('/professor/meus-slides/{id}/pptx', 'Integrations/SlidesController@baixarPptx');
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

// IMPORTANTE: Rotas mais específicas devem vir ANTES das mais genéricas

// Ver resumo (página única: ver resumo + nota + observações)
// Análise de Resumos: redireciona para Resultados > aba Resumos

// Rotas /professor/jornadas/modulos/... DEVEM vir ANTES de /professor/jornadas/{id}
// senão a URL /professor/jornadas/modulos/152/dica-professor é capturada por {id} = "modulos"


// Exercícios das Jornadas (rotas específicas já movidas para cima)

// Blocos de Conteúdo das Jornadas

// Buscar resumo específico (já movido para cima: analise-resumos)

// Dúvidas e Comunicação
// Redações da Jornada (Professor) - rotas específicas já movidas para cima

// EducaProf (chat) - construção de jornada via JSON (SSO professor)

// Mural → Modulos/mural/routes.php

// Teste OpenAI

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

// Redações → Modulos/redacoes/routes.php

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

// Diário de Classe: rotas em app/Modulos/diario/routes.php

// Planos de Aula (Professor)
$router->get('/professor/planos-aula', 'Education/LessonPlanController@index');
$router->get('/professor/planos-aula/criar', 'Education/LessonPlanController@criar');
$router->post('/professor/planos-aula/salvar', 'Education/LessonPlanController@salvar');
$router->post('/professor/planos-aula/copiloto', 'Education/LessonPlanController@gerarComCopiloto');
$router->get('/professor/planos-aula/visualizar/{id}', 'Education/LessonPlanController@visualizar');
$router->get('/professor/planos-aula/editar/{id}', 'Education/LessonPlanController@editar');
$router->post('/professor/planos-aula/atualizar/{id}', 'Education/LessonPlanController@atualizar');
$router->post('/professor/planos-aula/aprovar-rascunhos', 'Education/LessonPlanController@aprovarTodosRascunhos');
$router->post('/professor/planos-aula/duplicar/{id}', 'Education/LessonPlanController@duplicar');
$router->post('/professor/planos-aula/excluir/{id}', 'Education/LessonPlanController@excluir');
$router->get('/professor/planos-aula/pdf/{id}', 'Education/LessonPlanController@exportarPdf');

// Apostilas → Modulos/apostilas/routes.php

// IA da Apostila (módulo novo, independente do módulo Apostilas acima)

// Provas → Modulos/provas/routes.php

// Dashboard de Monitoramento (apenas admin) - real-time via SSE
$router->get('/monitoramento', 'MonitoramentoController@index');
$router->get('/api/infra', 'MonitoramentoController@apiInfra');
$router->get('/api/openai-usage', 'MonitoramentoController@apiOpenaiUsage');
$router->get('/api/users-stats', 'MonitoramentoController@apiUsersStats');
$router->get('/api/db-stats', 'MonitoramentoController@apiDbStats');
$router->get('/api/system-health', 'MonitoramentoController@apiSystemHealth');
$router->get('/api/monitoramento/stream', 'MonitoramentoController@apiStream');

// AVA → Modulos/ava/routes.php

// AVA / EAD - Professor: atividades, rubricas e correção (Fase 2)
// AVA / EAD - Professor: moderação de comentários por aula (Fase 2)

// AVA / EAD - Professor: aulas ao vivo (Fase 3)

// Calendário letivo
$router->get('/professor/calendario-letivo', 'User/TeacherController@calendarioLetivo');
