<?php
/**
 * Rotas autenticadas do Dashboard de Gestão Escolar.
 *
 * @var Router $router
 */

$router->get('/admin/dashboard', 'Modulos/dashboard-gestao/DashboardGestaoAdminController@index');
$router->get('/admin/dashboard/filtros', 'Modulos/dashboard-gestao/DashboardGestaoAdminController@filtros');
$router->get('/admin/dashboard/widget/{chave}', 'Modulos/dashboard-gestao/DashboardGestaoAdminController@widget');
