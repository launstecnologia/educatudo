<?php
/**
 * Rotas do módulo flashcards.
 *
 * @var Router $router
 */

$router->get('/flashcards', 'Modulos/flashcards/FlashcardController@index');
$router->get('/flashcards/create', 'Modulos/flashcards/FlashcardController@create');
$router->post('/flashcards/generate', 'Modulos/flashcards/FlashcardController@generate');
$router->get('/flashcards/gerando/{jobId}', 'Modulos/flashcards/FlashcardController@gerando');
$router->get('/flashcards/deck/{id}', 'Modulos/flashcards/FlashcardController@deck');
$router->get('/flashcards/explicar', 'Modulos/flashcards/FlashcardController@explicar');
$router->post('/flashcards/explicar/gerar', 'Modulos/flashcards/FlashcardController@explicarGerar');
