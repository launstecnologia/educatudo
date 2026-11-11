<?php
/**
 * Rotas do módulo ENAC Colag.
 *
 * @var \App\Core\Router $router
 */

$router->post(
    '/admin/provas/blocos/{id}/importar-enac-colag',
    'Modulos/enac-colag/EnacColagAdminController@importarNoBloco'
);
