<?php
/**
 * Demonstrativo estático do Colag. Outra escola é barrada no controller.
 *
 * @var Router $router
 */

$router->get('/admin/boletim-26-colag', 'Modulos/boletim-26-colag/Boletim26ColagAdminController@index');
$router->get('/admin/boletim-26-colag/parcial', 'Modulos/boletim-26-colag/Boletim26ColagAdminController@parcial');
$router->post('/admin/boletim-26-colag/situacao', 'Modulos/boletim-26-colag/Boletim26ColagAdminController@situacao');
$router->post('/admin/boletim-26-colag/homologar', 'Modulos/boletim-26-colag/Boletim26ColagAdminController@homologar');
$router->get('/admin/boletim-26-colag/exportar', 'Modulos/boletim-26-colag/Boletim26ColagAdminController@exportar');
$router->get('/admin/boletim-26-colag/exportar-prova-final', 'Modulos/boletim-26-colag/Boletim26ColagAdminController@exportarProvaFinal');
$router->get('/admin/boletim-26-colag/exportar-medias-bimestrais', 'Modulos/boletim-26-colag/Boletim26ColagAdminController@exportarMediasBimestrais');
$router->get('/admin/boletim-26-colag/exportar-medias-bimestrais-pdf', 'Modulos/boletim-26-colag/Boletim26ColagAdminController@exportarMediasBimestraisPdf');

$router->post('/admin/boletim-26-colag/publicacao', 'Modulos/boletim-26-colag/Boletim26ColagAdminController@publicarPortal');
