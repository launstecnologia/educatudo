<?php
/**
 * Captação, contrato e webhook ZapSign, sem sessão de admin.
 *
 * @var Router $router
 */

$router->get('/matricula/interesse', 'Modulos/matricula/MatriculaPublicoController@captacaoForm');
$router->post('/matricula/interesse', 'Modulos/matricula/MatriculaPublicoController@captacaoStore');
$router->get('/matricula/contrato/{token}', 'Modulos/matricula/MatriculaPublicoController@verContrato');
$router->post('/matricula/contrato/{token}/dados', 'Modulos/matricula/MatriculaPublicoController@confirmarDados');
$router->post('/matricula/contrato/{token}/assinar', 'Modulos/matricula/MatriculaPublicoController@assinar');
$router->get('/matricula/contrato/{token}/pdf', 'Modulos/matricula/MatriculaPublicoController@downloadPdf');
$router->post('/webhooks/zapsign/{tenant_slug}', 'Modulos/matricula/ZapSignWebhookController@handle');
$router->post('/webhooks/zapsign', 'Modulos/matricula/ZapSignWebhookController@handle');
