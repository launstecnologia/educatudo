<?php
/**
 * Feed de notícias, sem sessão.
 *
 * @var Router $router
 */

$router->get('/api/noticias', 'Noticias/NoticiasController@index');
