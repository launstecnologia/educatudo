<?php
/**
 * Rotas do módulo exercicios.
 *
 * @var Router $router
 */

$router->get('/admin/exercises', 'Modulos/exercicios/ExerciseAdminController@exercicios');
$router->get('/admin/exercises/create', 'Modulos/exercicios/ExerciseAdminController@criarExercicio');
$router->post('/admin/exercises', 'Modulos/exercicios/ExerciseAdminController@salvarExercicio');
$router->get('/admin/exercises/{id}/edit', 'Modulos/exercicios/ExerciseAdminController@editarExercicio');
$router->put('/admin/exercises/{id}', 'Modulos/exercicios/ExerciseAdminController@atualizarExercicio');
$router->delete('/admin/exercises/{id}', 'Modulos/exercicios/ExerciseAdminController@excluirExercicio');
$router->post('/admin/exercises/import', 'Modulos/exercicios/ExerciseAdminController@importarExercicios');
$router->get('/admin/exercises/export', 'Modulos/exercicios/ExerciseAdminController@exportarExercicios');
$router->get('/admin/exercises/{id}/export', 'Modulos/exercicios/ExerciseAdminController@exportarExercicios');
$router->get('/admin/exercises/{id}/questions', 'Modulos/exercicios/ExerciseAdminController@gerenciarQuestoes');
$router->get('/admin/exercises/{id}/questions/create', 'Modulos/exercicios/ExerciseAdminController@adicionarQuestao');
$router->post('/admin/exercises/{id}/questions', 'Modulos/exercicios/ExerciseAdminController@salvarQuestao');
$router->get('/admin/exercises/{listaId}/questions/{questaoId}/edit', 'Modulos/exercicios/ExerciseAdminController@editarQuestao');
$router->put('/admin/exercises/{listaId}/questions/{questaoId}', 'Modulos/exercicios/ExerciseAdminController@atualizarQuestao');
$router->delete('/admin/exercises/{listaId}/questions/{questaoId}', 'Modulos/exercicios/ExerciseAdminController@excluirQuestao');
$router->post('/admin/exercises/{id}/questions/reorder', 'Modulos/exercicios/ExerciseAdminController@reordenarQuestoes');

$router->get('/exercicios', 'Exercises/ExerciseController@index');
$router->get('/exercicios/iniciar', 'Exercises/ExerciseController@iniciar');
$router->post('/exercicios/responder', 'Exercises/ExerciseController@responder');
$router->get('/exercicios/finalizar', 'Exercises/ExerciseController@finalizar');
$router->post('/exercicios/finalizar', 'Exercises/ExerciseController@finalizar');
$router->get('/exercicios/resultado', 'Exercises/ExerciseController@resultado');
$router->get('/exercicios/historico', 'Exercises/ExerciseController@historico');

$router->get('/exercicios-personalizados', 'Exercises/CustomExerciseController@index');
$router->get('/exercicios-personalizados/criar', 'Exercises/CustomExerciseController@criar');
$router->post('/exercicios-personalizados/gerar', 'Exercises/CustomExerciseController@gerarExercicios');
$router->post('/exercicios-personalizados/importar-questoes-ia/{jobId}', 'Exercises/CustomExerciseController@importarQuestoesIA');
$router->post('/exercicios-personalizados/erro-geracao-ia', 'Exercises/CustomExerciseController@erroGeracaoIA');
$router->post('/exercicios-personalizados/tentar-novamente/{listaId}', 'Exercises/CustomExerciseController@tentarNovamente');
$router->get('/exercicios-personalizados/minhas-listas', 'Exercises/CustomExerciseController@listaMinhasListas');
$router->get('/exercicios-personalizados/status', 'Exercises/CustomExerciseController@verificarStatus');
$router->post('/exercicios-personalizados/iniciar', 'Exercises/CustomExerciseController@iniciarExecucao');
$router->get('/exercicios-personalizados/executar', 'Exercises/CustomExerciseController@executar');
$router->post('/exercicios-personalizados/executar', 'Exercises/CustomExerciseController@executar');
$router->post('/exercicios-personalizados/responder', 'Exercises/CustomExerciseController@responderQuestao');
$router->post('/exercicios-personalizados/finalizar', 'Exercises/CustomExerciseController@finalizar');
$router->get('/exercicios-personalizados/finalizar', 'Exercises/CustomExerciseController@finalizar');
$router->get('/exercicios-personalizados/resultados', 'Exercises/CustomExerciseController@resultados');
$router->get('/exercicios-personalizados/historico', 'Exercises/CustomExerciseController@historico');
