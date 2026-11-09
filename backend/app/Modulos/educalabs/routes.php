<?php
/**
 * EducaLabs do aluno (com login).
 *
 * @var Router $router
 */

$router->get('/educalabs/access', 'EducaLabs/EducaLabsController@access');
$router->get('/educalabs', 'EducaLabs/EducaLabsController@index');
$router->get('/educalabs/novo', 'EducaLabs/EducaLabsController@create');
$router->post('/educalabs/salvar', 'EducaLabs/EducaLabsController@store');
$router->get('/educalabs/projetos/{id}', 'EducaLabs/EducaLabsController@show');
$router->get('/educalabs/projetos/{id}/preview', 'EducaLabs/EducaLabsController@preview');
$router->get('/educalabs/projetos/{id}/preview/{file}', 'EducaLabs/EducaLabsController@previewFile');
$router->post('/educalabs/projetos/{id}/mensagem', 'EducaLabs/EducaLabsController@addMessage');
$router->post('/educalabs/projetos/{id}/gerar', 'EducaLabs/EducaLabsController@generate');
