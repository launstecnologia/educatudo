<?php
/**
 * Manifest do módulo ENAC Colag (importação de pontuação ENAC → nota).
 * Default off — ligar só no tenant COLAG pelo Painel Master.
 */
return [
    'chave' => 'enac-colag',
    'namespace' => 'App\\Modulos\\EnacColag',
    'label' => 'ENAC Colag',
    'grupo' => 'colag',
    'feature_keys' => [
        'admin' => 'enac_colag',
    ],
    'master_feature_keys' => ['enac_colag'],
    'master_form_key' => 'geral_enac_colag',
    'feature_defaults' => [
        'enac_colag' => '0',
    ],
    'feature_gate' => true,
    // Sem rota própria: a importação vive em /admin/provas/blocos/{id}/importar-enac-colag.
    // Não registrar prefixo em /admin/provas (quebraria o restante). Gate no controller/view.
    'rotas' => [],
    'menu' => [],
    'controllers_dir' => __DIR__ . '/Controllers',
];
