<?php
/**
 * Token do portal de jogos, sem sessão.
 *
 * @var Router $router
 */

$router->get('/games/validate-token', 'Games/GameController@validateToken');
$router->post('/games/logout', 'Games/GameController@logoutToken');
