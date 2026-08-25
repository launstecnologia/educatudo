<?php
/**
 * Rotas autenticadas do módulo Gestão de Presença.
 * Webhook público fica em config/routes/public.php.
 *
 * @var Router $router
 */

$router->get('/admin/presenca', 'Modulos/presenca/PresencaAdminController@index');
$router->post('/admin/presenca/registrar', 'Modulos/presenca/PresencaAdminController@registrar');
$router->get('/admin/presenca/alunos', 'Modulos/presenca/PresencaAdminController@buscarAlunos');
$router->get('/admin/presenca/linha-do-tempo', 'Modulos/presenca/PresencaAdminController@linhaDoTempo');
$router->get('/admin/presenca/config', 'Modulos/presenca/PresencaAdminController@config');
$router->post('/admin/presenca/config', 'Modulos/presenca/PresencaAdminController@salvarConfig');
$router->post('/admin/presenca/integracoes', 'Modulos/presenca/PresencaAdminController@criarIntegracao');
$router->post('/admin/presenca/integracoes/ativo', 'Modulos/presenca/PresencaAdminController@toggleIntegracao');
$router->post('/admin/presenca/identificadores', 'Modulos/presenca/PresencaAdminController@criarIdentificador');
$router->post('/admin/presenca/identificadores/excluir', 'Modulos/presenca/PresencaAdminController@excluirIdentificador');
