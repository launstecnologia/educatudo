<?php
/**
 * Página pública do stand (QR), sem login.
 *
 * @var Router $router
 */

$router->get('/expo-colag/s/{token}', 'Modulos/expo-colag/ExpoColagPublicoController@stand');
$router->post('/expo-colag/s/{token}/avaliar', 'Modulos/expo-colag/ExpoColagPublicoController@avaliarStand');
$router->get('/expo-colag/midia/{id}/capa', 'Modulos/expo-colag/ExpoColagPublicoController@capa');
