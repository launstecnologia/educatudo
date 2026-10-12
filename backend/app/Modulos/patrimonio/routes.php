<?php
/**
 * Rotas admin do módulo Patrimônio (almoxarifado e bens).
 *
 * @var Router $router
 */

$router->get('/admin/almoxarifado', 'Modulos/patrimonio/InventoryAdminController@index');
$router->post('/admin/almoxarifado/itens', 'Modulos/patrimonio/InventoryAdminController@storeItem');
$router->post('/admin/almoxarifado/depositos', 'Modulos/patrimonio/InventoryAdminController@storeWarehouse');
$router->post('/admin/almoxarifado/fornecedores', 'Modulos/patrimonio/InventoryAdminController@storeSupplier');
$router->post('/admin/almoxarifado/movimentacoes', 'Modulos/patrimonio/InventoryAdminController@storeMovement');
$router->post('/admin/almoxarifado/requisicoes', 'Modulos/patrimonio/InventoryAdminController@storeRequisition');
$router->post('/admin/almoxarifado/requisicoes/{id}/aprovar', 'Modulos/patrimonio/InventoryAdminController@approve');
$router->post('/admin/almoxarifado/requisicoes/{id}/rejeitar', 'Modulos/patrimonio/InventoryAdminController@reject');
$router->post('/admin/almoxarifado/requisicoes/{id}/atender', 'Modulos/patrimonio/InventoryAdminController@fulfill');
$router->get('/admin/patrimonio', 'Modulos/patrimonio/PatrimonyAdminController@index');
$router->post('/admin/patrimonio/ambientes', 'Modulos/patrimonio/PatrimonyAdminController@storeLocation');
$router->post('/admin/patrimonio/bens', 'Modulos/patrimonio/PatrimonyAdminController@storeAsset');
$router->post('/admin/patrimonio/movimentacoes', 'Modulos/patrimonio/PatrimonyAdminController@moveAsset');
$router->post('/admin/patrimonio/conferencias', 'Modulos/patrimonio/PatrimonyAdminController@checkAsset');
