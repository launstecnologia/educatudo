<?php
/**
 * Token do portal Notes, sem sessão.
 *
 * @var Router $router
 */

$router->get('/notes/validate-token', 'Notes/NotesTokenController@validateToken');
$router->post('/notes/logout', 'Notes/NotesTokenController@logoutToken');
