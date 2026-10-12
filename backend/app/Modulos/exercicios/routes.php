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
