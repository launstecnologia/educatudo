<?php

$router->get('/admin/documentos-familia', 'Modulos/documentos-familia/DocumentoFamiliaAdminController@index');
$router->get('/admin/documentos-familia/arquivos/{id}', 'Modulos/documentos-familia/DocumentoFamiliaAdminController@arquivo');
$router->post('/admin/documentos-familia/declaracoes', 'Modulos/documentos-familia/DocumentoFamiliaAdminController@criarDeclaracao');
