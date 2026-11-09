<?php
/**
 * Rotas autenticadas do Notes: portal externo e Meu Caderno.
 *
 * @var Router $router
 */

$router->get('/notes/access', 'Notes/NotesController@access');
$router->get('/notes/access-url', 'Notes/NotesController@accessUrl');

$router->get('/caderno', 'Modulos/notes/CadernoController@index');
$router->get('/caderno/novo', 'Modulos/notes/CadernoController@create');
$router->get('/caderno/novo-excalidraw', 'Modulos/notes/CadernoController@createExcalidraw');
$router->post('/caderno', 'Modulos/notes/CadernoController@store');
$router->post('/caderno/pasta', 'Modulos/notes/CadernoController@storePasta');
$router->post('/caderno/pasta/{id}', 'Modulos/notes/CadernoController@updatePasta');
$router->post('/caderno/pasta/{id}/excluir', 'Modulos/notes/CadernoController@destroyPasta');
$router->get('/caderno/{id}/editar', 'Modulos/notes/CadernoController@edit');
$router->get('/caderno/{id}/excalidraw-editor', 'Modulos/notes/CadernoController@excalidrawEditor');
$router->get('/caderno/{id}/excalidraw-view', 'Modulos/notes/CadernoController@excalidrawView');
$router->get('/caderno/{id}/excalidraw-carregar', 'Modulos/notes/CadernoController@excalidrawCarregar');
$router->post('/caderno/{id}/excalidraw-salvar', 'Modulos/notes/CadernoController@excalidrawSalvar');
$router->post('/caderno/{id}/excluir', 'Modulos/notes/CadernoController@destroy');
$router->post('/caderno/anexo/excluir', 'Modulos/notes/CadernoController@excluirAnexo');
$router->post('/caderno/anexo/salvar-anotacao', 'Modulos/notes/CadernoController@salvarAnotacaoAnexo');
$router->get('/caderno/{caderno_id}/anexo/{anexo_id}/anotar', 'Modulos/notes/CadernoController@anotarAnexo');
$router->get('/caderno/{caderno_id}/anexo/{anexo_id}', 'Modulos/notes/CadernoController@anexo');
$router->get('/caderno/{id}', 'Modulos/notes/CadernoController@show');
$router->post('/caderno/{id}', 'Modulos/notes/CadernoController@update');
