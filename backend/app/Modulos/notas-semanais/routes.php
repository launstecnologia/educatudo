<?php
/**
 * Rotas do módulo Quadro de Notas Semanais.
 *
 * @var Router $router
 */

$router->get('/admin/notas-semanais', 'Modulos/notas-semanais/NotasSemanaisAdminController@config');
$router->post('/admin/notas-semanais', 'Modulos/notas-semanais/NotasSemanaisAdminController@salvar');
