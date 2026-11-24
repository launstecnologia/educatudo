<?php
/**
 * Boletim Colag. O assistente em /admin/boletins lê o modelo daqui
 * quando o tenant é colag.
 *
 * @var Router $router
 */

$router->get('/admin/boletim-colag', 'Modulos/boletim-colag/BoletimColagAdminController@index');
