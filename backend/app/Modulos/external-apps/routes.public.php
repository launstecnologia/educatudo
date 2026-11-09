<?php
/**
 * Abertura e créditos dos apps externos.
 *
 * @var Router $router
 */

$router->get('/external-apps/abrir/{id}', 'ExternalApps/ExternalAppsController@abrir');
$router->get('/external-apps/validate-token', 'ExternalApps/ExternalAppsController@validateToken');
$router->post('/external-apps/validate-token', 'ExternalApps/ExternalAppsController@validateToken');
$router->get('/external-apps/credit-context', 'ExternalApps/ExternalAppsController@creditContext');
$router->post('/external-apps/credit-context', 'ExternalApps/ExternalAppsController@creditContext');
$router->post('/external-apps/consume', 'ExternalApps/ExternalAppsController@consumeCredit');
$router->post('/external-apps/refund', 'ExternalApps/ExternalAppsController@refundCredit');
