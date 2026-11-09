<?php
/**
 * Webhook de catraca/reconhecimento, sem sessão.
 *
 * @var Router $router
 */

$router->post('/api/webhooks/presenca', 'Modulos/presenca/PresencaWebhookController@receber');
$router->post('/api/webhooks/presenca/{provedor}', 'Modulos/presenca/PresencaWebhookController@receber');
