<?php
/**
 * Compartilhamento público do EducaLabs.
 *
 * @var Router $router
 */

$router->get('/educalabs/public/{share_id}', 'EducaLabs/EducaLabsController@publicView');
$router->get('/educalabs/public/{share_id}/styles.css', 'EducaLabs/EducaLabsController@publicAsset');
$router->get('/educalabs/public/{share_id}/script.js', 'EducaLabs/EducaLabsController@publicAsset');
$router->get('/educalabs/validate-token', 'EducaLabs/EducaLabsController@validateToken');
$router->post('/educalabs/logout', 'EducaLabs/EducaLabsController@logoutToken');
