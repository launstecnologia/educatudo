<?php
/**
 * Captura da folha pelo celular, com token assinado.
 *
 * @var Router $router
 */

$router->get('/captura-gabarito/{token}', 'Modulos/leitor-gabarito/CapturaGabaritoController@abrir');
$router->post('/captura-gabarito/{token}', 'Modulos/leitor-gabarito/CapturaGabaritoController@enviar');
