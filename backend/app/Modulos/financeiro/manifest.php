<?php
/**
 * Manifest do módulo Financeiro escolar.
 */
return [
    'chave' => 'financeiro',
    'label' => 'Financeiro',
    'namespace' => 'App\Modulos\Financeiro',
    'grupo' => 'gestao_escolar',
    'feature_keys' => [
        'admin' => 'financeiro',
    ],
    'master_feature_keys' => ['financeiro'],
    'master_form_key' => 'geral_financeiro',
    'feature_defaults' => [
        'financeiro' => '1',
    ],
    'feature_gate' => false,
    'rotas' => [
        '/admin/finance' => 'financeiro',
    ],
    'controllers_dir' => __DIR__ . '/Controllers',
    'views_dir' => __DIR__ . '/Views',
];
