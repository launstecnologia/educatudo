<?php
/**
 * Rotas do módulo Drive (aluno e professor).
 *
 * @var Router $router
 */

// Aluno
$router->get('/drive', 'Modulos/drive/DriveController@index');
$router->get('/drive/folder/{id}', 'Modulos/drive/DriveController@folder');
$router->post('/drive/create-folder', 'Modulos/drive/DriveController@createFolder');
$router->post('/drive/upload', 'Modulos/drive/DriveController@upload');
$router->post('/drive/rename', 'Modulos/drive/DriveController@rename');
$router->post('/drive/delete', 'Modulos/drive/DriveController@delete');
$router->post('/drive/share', 'Modulos/drive/DriveController@share');
$router->post('/drive/unshare', 'Modulos/drive/DriveController@unshare');
$router->get('/drive/view/{id}', 'Modulos/drive/DriveController@viewFile');
$router->get('/drive/serve/{id}', 'Modulos/drive/DriveController@serve');
$router->get('/drive/download/{id}', 'Modulos/drive/DriveController@download');
$router->get('/drive/search-users', 'Modulos/drive/DriveController@searchUsers');
$router->get('/drive/share-list/{id}', 'Modulos/drive/DriveController@shareList');

// Professor
$router->get('/professor/drive', 'Modulos/drive/DriveController@index');
$router->get('/professor/drive/folder/{id}', 'Modulos/drive/DriveController@folder');
$router->post('/professor/drive/create-folder', 'Modulos/drive/DriveController@createFolder');
$router->post('/professor/drive/upload', 'Modulos/drive/DriveController@upload');
$router->post('/professor/drive/rename', 'Modulos/drive/DriveController@rename');
$router->post('/professor/drive/delete', 'Modulos/drive/DriveController@delete');
$router->post('/professor/drive/share', 'Modulos/drive/DriveController@share');
$router->post('/professor/drive/unshare', 'Modulos/drive/DriveController@unshare');
$router->get('/professor/drive/view/{id}', 'Modulos/drive/DriveController@viewFile');
$router->get('/professor/drive/serve/{id}', 'Modulos/drive/DriveController@serve');
$router->get('/professor/drive/download/{id}', 'Modulos/drive/DriveController@download');
$router->get('/professor/drive/search-users', 'Modulos/drive/DriveController@searchUsers');
$router->get('/professor/drive/share-list/{id}', 'Modulos/drive/DriveController@shareList');
