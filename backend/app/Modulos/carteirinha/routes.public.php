<?php
/**
 * Validação pública da carteirinha, sem login.
 *
 * @var Router $router
 */

$router->get('/validar/carteirinha/{token}', 'Modulos/carteirinha/CarteirinhaPublicoController@validar');
$router->get('/carteirinha/{token}', 'Modulos/carteirinha/CarteirinhaPublicoController@ver');
