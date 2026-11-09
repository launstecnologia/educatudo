<?php
/**
 * Redirecionamento do aluno ao portal EducaHits.
 *
 * @var Router $router
 */

$router->get('/hits', 'EducaHits/EducaHitsPortalController@toPortal');
$router->get('/hits/solicitar', 'EducaHits/EducaHitsPortalController@toSolicitar');
$router->get('/hits/demo-player', 'EducaHits/EducaHitsPortalController@toPortal');
$router->get('/hits/request', 'EducaHits/EducaHitsPortalController@toSolicitar');
$router->post('/hits/request', 'EducaHits/EducaHitsPortalController@submitRequest');
$router->get('/hits/my-requests', 'EducaHits/EducaHitsPortalController@toPortal');
$router->get('/hits/track/{id}', 'EducaHits/EducaHitsPortalController@toPortal');
