<?php
/**
 * Rotas autenticadas do módulo Matrícula (admin).
 * Rotas públicas (/matricula/*) ficam em config/routes/public.php.
 *
 * @var Router $router
 */

// Alias PT + legado EN (/admin/enrollment)
foreach (['/admin/enrollment', '/admin/matricula'] as $base) {
    $router->get($base, 'Modulos/matricula/MatriculaAdminController@index');
    $router->get($base . '/config', 'Modulos/matricula/MatriculaAdminController@configForm');
    $router->post($base . '/config', 'Modulos/matricula/MatriculaAdminController@configStore');
    $router->get($base . '/create', 'Modulos/matricula/MatriculaAdminController@create');
    $router->post($base, 'Modulos/matricula/MatriculaAdminController@store');
    $router->get($base . '/plano/{id}/itens', 'Modulos/matricula/MatriculaAdminController@planoItens');
    $router->get($base . '/rematricula-lote', 'Modulos/matricula/MatriculaAdminController@rematriculaLoteForm');
    $router->post($base . '/rematricula-lote', 'Modulos/matricula/MatriculaAdminController@rematriculaLoteStore');
    $router->get($base . '/score', 'Modulos/matricula/MatriculaAdminController@scorePanel');
    $router->post($base . '/score/recalcular', 'Modulos/matricula/MatriculaAdminController@recalcularScores');
    $router->get($base . '/{id}', 'Modulos/matricula/MatriculaAdminController@show');
    $router->get($base . '/{id}/edit', 'Modulos/matricula/MatriculaAdminController@edit');
    $router->post($base . '/{id}/edit', 'Modulos/matricula/MatriculaAdminController@update');
    $router->post($base . '/{id}/contrato', 'Modulos/matricula/MatriculaAdminController@gerarContrato');
    $router->post($base . '/{id}/contratos/{regraId}/gerar', 'Modulos/matricula/MatriculaAdminController@gerarContratoRegra');
    $router->get($base . '/{id}/contratos/{contratoId}/download', 'Modulos/matricula/MatriculaAdminController@downloadContratoRegra');
    $router->get($base . '/{id}/contrato/download', 'Modulos/matricula/MatriculaAdminController@downloadContrato');
    $router->post($base . '/{id}/contrato-assinado', 'Modulos/matricula/MatriculaAdminController@uploadContratoAssinado');
    $router->post($base . '/{id}/zapsign/sincronizar', 'Modulos/matricula/MatriculaAdminController@sincronizarZapSign');
    $router->post($base . '/{id}/status', 'Modulos/matricula/MatriculaAdminController@transicionar');
    $router->post($base . '/{id}/cancelar', 'Modulos/matricula/MatriculaAdminController@cancelar');
    $router->post($base . '/{id}/documentos', 'Modulos/matricula/MatriculaAdminController@uploadDocumento');
    $router->post($base . '/{id}/documentos/{docId}/remover', 'Modulos/matricula/MatriculaAdminController@removerDocumento');
}

// Configurações globais (Z-Configuração)
$router->get('/admin/configuracao/assinatura-digital', 'Modulos/matricula/MatriculaAdminController@assinaturaDigitalForm');
$router->post('/admin/configuracao/assinatura-digital', 'Modulos/matricula/MatriculaAdminController@assinaturaDigitalStore');
$router->get('/admin/configuracao/matricula', 'Modulos/matricula/MatriculaAdminController@configForm');
$router->post('/admin/configuracao/matricula', 'Modulos/matricula/MatriculaAdminController@configStore');

