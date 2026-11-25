<?php
/**
 * Demonstrativo estático do Colag. Outra escola é barrada no controller.
 *
 * @var Router $router
 */

$router->get('/admin/boletim-26-colag', 'Modulos/boletim-26-colag/Boletim26ColagAdminController@index');
$router->post('/admin/boletim-26-colag/homologar', 'Modulos/boletim-26-colag/Boletim26ColagAdminController@homologar');
$router->get('/admin/boletim-26-colag/exportar', 'Modulos/boletim-26-colag/Boletim26ColagAdminController@exportar');
