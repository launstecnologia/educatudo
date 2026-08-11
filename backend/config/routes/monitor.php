<?php
// Rotas para Monitor de sala (perfil: monitor)
$router->get('/monitor/dashboard', 'User/MonitorController@dashboard');
$router->get('/monitor/alterar-senha-obrigatoria', 'User/MonitorController@alterarSenhaObrigatoria');
$router->post('/monitor/alterar-senha-obrigatoria', 'User/MonitorController@processarAlteracaoSenha');
$router->get('/monitor/api/alunos-online', 'User/MonitorController@apiAlunosOnline');
$router->get('/monitor/api/alunos-online/stream', 'User/MonitorController@apiAlunosOnlineStream');
$router->get('/monitor/aluno/{id}', 'User/MonitorController@verAluno');
$router->get('/monitor/aluno/{alunoId}/jornada/{jornadaId}', 'User/MonitorController@verJornada');
$router->get('/monitor/aluno/{alunoId}/prova/{provaId}', 'User/MonitorController@verProva');
$router->post('/monitor/aluno/{id}/senha', 'User/MonitorController@updateStudentPassword');
