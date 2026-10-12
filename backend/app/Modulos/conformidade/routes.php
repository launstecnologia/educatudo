<?php
/**
 * Rotas admin do módulo Conformidade.
 *
 * @var Router $router
 */

$router->get('/admin/conformidade', 'Modulos/conformidade/ComplianceController@dashboard');
$router->get('/admin/conformidade/pendencias', 'Modulos/conformidade/ComplianceController@pendencias');
$router->get('/admin/conformidade/auditoria', 'Modulos/conformidade/ComplianceController@auditoria');
$router->get('/admin/conformidade/ia', 'Modulos/conformidade/ComplianceController@ia');
$router->post('/admin/conformidade/ia/perguntar', 'Modulos/conformidade/ComplianceController@iaPerguntar');
