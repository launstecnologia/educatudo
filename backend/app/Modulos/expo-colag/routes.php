<?php
/**
 * Rotas do módulo Expo Colag (aluno, professor, admin).
 * Página pública do QR fica em config/routes/public.php (sem Auth).
 *
 * @var Router $router
 */

// Aluno — mural / inscrição / painel / programação
$router->get('/expo-colag', 'Modulos/expo-colag/ExpoColagAlunoController@index');
$router->get('/expo-colag/programacao', 'Modulos/expo-colag/ExpoColagAlunoController@programacao');
$router->get('/expo-colag/projeto/{id}', 'Modulos/expo-colag/ExpoColagAlunoController@projeto');
$router->get('/expo-colag/projeto/{id}/painel', 'Modulos/expo-colag/ExpoColagAlunoController@painel');
$router->post('/expo-colag/projeto/{id}/inscrever', 'Modulos/expo-colag/ExpoColagAlunoController@inscrever');
$router->post('/expo-colag/projeto/{id}/cancelar-inscricao', 'Modulos/expo-colag/ExpoColagAlunoController@cancelarInscricao');
$router->post('/expo-colag/projeto/{id}/entregar-tarefa', 'Modulos/expo-colag/ExpoColagAlunoController@entregarTarefa');
$router->post('/expo-colag/projeto/{id}/solicitar-material', 'Modulos/expo-colag/ExpoColagAlunoController@solicitarMaterial');
$router->post('/expo-colag/projeto/{id}/mensagens', 'Modulos/expo-colag/ExpoColagAlunoController@enviarMensagem');

// Professor — criar / acompanhar / tarefas / materiais / stand
$router->get('/professor/expo-colag', 'Modulos/expo-colag/ExpoColagProfessorController@index');
$router->get('/professor/expo-colag/criar', 'Modulos/expo-colag/ExpoColagProfessorController@criar');
$router->get('/professor/expo-colag/projetos', 'Modulos/expo-colag/ExpoColagProfessorController@projetos');
$router->get('/professor/expo-colag/projetos/{id}/editar', 'Modulos/expo-colag/ExpoColagProfessorController@editar');
$router->get('/professor/expo-colag/projetos/{id}/preview', 'Modulos/expo-colag/ExpoColagProfessorController@preview');
$router->get('/professor/expo-colag/projetos/{id}/materiais-pdf', 'Modulos/expo-colag/ExpoColagProfessorController@materiaisPdf');
$router->get('/professor/expo-colag/projetos/{id}/acompanhar', 'Modulos/expo-colag/ExpoColagProfessorController@acompanhar');
$router->post('/professor/expo-colag/projetos/salvar', 'Modulos/expo-colag/ExpoColagProfessorController@salvar');
$router->post('/professor/expo-colag/projetos/{id}/publicar', 'Modulos/expo-colag/ExpoColagProfessorController@publicar');
$router->post('/professor/expo-colag/projetos/{id}/excluir', 'Modulos/expo-colag/ExpoColagProfessorController@excluir');
$router->post('/professor/expo-colag/projetos/{id}/inscricoes/decidir', 'Modulos/expo-colag/ExpoColagProfessorController@decidirInscricao');
$router->post('/professor/expo-colag/projetos/{id}/tarefas', 'Modulos/expo-colag/ExpoColagProfessorController@criarTarefa');
$router->post('/professor/expo-colag/projetos/{id}/tarefas/excluir', 'Modulos/expo-colag/ExpoColagProfessorController@excluirTarefa');
$router->post('/professor/expo-colag/projetos/{id}/tarefas/decidir', 'Modulos/expo-colag/ExpoColagProfessorController@decidirAtribuicao');
$router->post('/professor/expo-colag/projetos/{id}/materiais', 'Modulos/expo-colag/ExpoColagProfessorController@adicionarMaterial');
$router->post('/professor/expo-colag/projetos/{id}/materiais/remover', 'Modulos/expo-colag/ExpoColagProfessorController@removerMaterial');
$router->post('/professor/expo-colag/projetos/{id}/pedidos-materiais/decidir', 'Modulos/expo-colag/ExpoColagProfessorController@decidirPedidoMaterial');
$router->post('/professor/expo-colag/projetos/{id}/mensagens', 'Modulos/expo-colag/ExpoColagProfessorController@enviarMensagem');
$router->post('/professor/expo-colag/projetos/{id}/stand', 'Modulos/expo-colag/ExpoColagProfessorController@salvarStand');
$router->post('/professor/expo-colag/projetos/rascunho', 'Modulos/expo-colag/ExpoColagProfessorController@salvarRascunho');
$router->get('/professor/expo-colag/alunos-turma', 'Modulos/expo-colag/ExpoColagProfessorController@alunosTurma');
$router->get('/professor/expo-colag/bncc', 'Modulos/expo-colag/ExpoColagProfessorController@buscarBncc');

// Admin / coordenação
$router->get('/admin/expo-colag', 'Modulos/expo-colag/ExpoColagAdminController@index');
$router->get('/admin/expo-colag/configuracao', 'Modulos/expo-colag/ExpoColagAdminController@configuracao');
$router->post('/admin/expo-colag/configuracao', 'Modulos/expo-colag/ExpoColagAdminController@salvarConfiguracao');
$router->get('/admin/expo-colag/autorizacoes', 'Modulos/expo-colag/ExpoColagAdminController@autorizacoes');
$router->post('/admin/expo-colag/autorizacoes', 'Modulos/expo-colag/ExpoColagAdminController@salvarAutorizacao');
$router->get('/admin/expo-colag/programacao', 'Modulos/expo-colag/ExpoColagAdminController@programacao');
$router->post('/admin/expo-colag/programacao', 'Modulos/expo-colag/ExpoColagAdminController@salvarProgramacao');
